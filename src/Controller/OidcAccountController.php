<?php
namespace UserFrosting\Sprinkle\OidcUser\Controller;

use UserFrosting\Sprinkle\Account\Controller\AccountController;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use UserFrosting\Fortress\RequestDataTransformer;
use UserFrosting\Fortress\RequestSchema;
use UserFrosting\Fortress\ServerSideValidator;
use UserFrosting\Sprinkle\Account\Controller\Exception\SpammyRequestException;
use UserFrosting\Sprinkle\Account\Util\Password;
use UserFrosting\Sprinkle\Core\Mail\EmailRecipient;
use UserFrosting\Sprinkle\Core\Mail\TwigMailMessage;
use UserFrosting\Sprinkle\Core\Util\Captcha;
use UserFrosting\Support\Exception\HttpException;

class OidcAccountController extends AccountController{
    /**
     * Processes an new account registration request.
     *
     * This is throttled to prevent account enumeration, since it needs to divulge when a username/email has been used.
     * Processes the request from the form on the registration page, checking that:
     * 1. The honeypot was not modified;
     * 2. The master account has already been created (during installation);
     * 3. Account registration is enabled;
     * 4. The user is not already logged in;
     * 5. Valid information was entered;
     * 6. The captcha, if enabled, is correct;
     * 7. The username and email are not already taken.
     * Automatically sends an activation link upon success, if account activation is enabled.
     * This route is "public access".
     * Request type: POST
     * Returns the User Object for the user record that was created.
     */
    public function register(Request $request, Response $response, $args)
    {
        /** @var MessageStream $ms */
        $ms = $this->ci->alerts;

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        /** @var UserFrosting\Config\Config $config */
        $config = $this->ci->config;

        // Get POST parameters: user_name, first_name, last_name, email, password, passwordc, captcha, spiderbro, csrf_token
        $params = $request->getParsedBody();

        // Check the honeypot. 'spiderbro' is not a real field, it is hidden on the main page and must be submitted with its default value for this to be processed.
        if (!isset($params['spiderbro']) || $params['spiderbro'] != 'http://') {
            throw new SpammyRequestException('Possible spam received:' . print_r($params, true));
        }

        // Security measure: do not allow registering new users until the master account has been created.
        if (!$classMapper->staticMethod('user', 'find', $config['reserved_user_ids.master'])) {
            $ms->addMessageTranslated('danger', 'ACCOUNT.MASTER_NOT_EXISTS');
            return $response->withStatus(403);
        }

        // Check if registration is currently enabled
        if (!$config['site.registration.enabled']) {
            $ms->addMessageTranslated('danger', 'REGISTRATION.DISABLED');
            return $response->withStatus(403);
        }

        /** @var UserFrosting\Sprinkle\Account\Authenticate\Authenticator $authenticator */
        $authenticator = $this->ci->authenticator;

        // Prevent the user from registering if he/she is already logged in
        if ($authenticator->check()) {
            $ms->addMessageTranslated('danger', 'REGISTRATION.LOGOUT');
            return $response->withStatus(403);
        }

        // Load the request schema
        $schema = new RequestSchema('schema://requests/register.yaml');

        // Whitelist and set parameter defaults
        $transformer = new RequestDataTransformer($schema);
        $data = $transformer->transform($params);

        $error = false;

        // Validate request data
        $validator = new ServerSideValidator($schema, $this->ci->translator);
        if (!$validator->validate($data)) {
            $ms->addValidationErrors($validator);
            $error = true;
        }

        /** @var UserFrosting\Sprinkle\Core\Throttle\Throttler $throttler */
        $throttler = $this->ci->throttler;
        $delay = $throttler->getDelay('registration_attempt');

        // Throttle requests
        if ($delay > 0) {
            return $response->withStatus(429);
        }

        // Check if username or email already exists
        if ($classMapper->staticMethod('user', 'findUnique', $data['user_name'], 'user_name')) {
            $ms->addMessageTranslated('danger', 'USERNAME.IN_USE', $data);
            $error = true;
        }

        if ($classMapper->staticMethod('user', 'findUnique', $data['email'], 'email')) {
            $ms->addMessageTranslated('danger', 'EMAIL.IN_USE', $data);
            $error = true;
        }

        // Check captcha, if required
        if ($config['site.registration.captcha']) {
            $captcha = new Captcha($this->ci->session, $this->ci->config['session.keys.captcha']);
            if (!$data['captcha'] || !$captcha->verifyCode($data['captcha'])) {
                $ms->addMessageTranslated('danger', 'CAPTCHA.FAIL');
                $error = true;
            }
        }

        if ($error) {
            return $response->withStatus(400);
        }

        // Remove captcha, password confirmation from object data after validation
        unset($data['captcha']);
        unset($data['passwordc']);

        if ($config['site.registration.require_email_verification']) {
            $data['flag_verified'] = false;
        } else {
            $data['flag_verified'] = true;
        }

        // Load default group
        $groupSlug = $config['site.registration.user_defaults.group'];
        $defaultGroup = $classMapper->staticMethod('group', 'where', 'slug', $groupSlug)->first();

        if (!$defaultGroup) {
            $e = new HttpException("Account registration is not working because the default group '$groupSlug' does not exist.");
            $e->addUserMessage('ACCOUNT.REGISTRATION_BROKEN');
            throw $e;
        }

        // Set default group
        $data['group_id'] = $defaultGroup->id;

        // Set default locale
        $data['locale'] = $config['site.registration.user_defaults.locale'];

        // Hash password
        $data['password'] = Password::hash($data['password']);

        // Add uuid
        $data['sub'] = $this->ci->uuidGenerate->toString();

        // All checks passed!  log events/activities, create user, and send verification email (if required)
        // Begin transaction - DB will be rolled back if an exception occurs
        Capsule::transaction( function() use ($classMapper, $data, $ms, $config, $throttler) {
            // Log throttleable event
            $throttler->logEvent('registration_attempt');

            // Create the user
            $user = $classMapper->createInstance('user', $data);

            // Store new user to database
            $user->save();

            // Create activity record
            $this->ci->userActivityLogger->info("User {$user->user_name} registered for a new account.", [
                'type' => 'sign_up',
                'user_id' => $user->id
            ]);

            // Load default roles
            $defaultRoleSlugs = $classMapper->staticMethod('role', 'getDefaultSlugs');
            $defaultRoles = $classMapper->staticMethod('role', 'whereIn', 'slug', $defaultRoleSlugs)->get();
            $defaultRoleIds = $defaultRoles->pluck('id')->all();

            // Attach default roles
            $user->roles()->attach($defaultRoleIds);

            // Verification email
            if ($config['site.registration.require_email_verification']) {
                // Try to generate a new verification request
                $verification = $this->ci->repoVerification->create($user, $config['verification.timeout']);

                // Create and send verification email
                $message = new TwigMailMessage($this->ci->view, 'mail/verify-account.html.twig');

                $message->from($config['address_book.admin'])
                    ->addEmailRecipient(new EmailRecipient($user->email, $user->full_name))
                    ->addParams([
                        'user' => $user,
                        'token' => $verification->getToken()
                    ]);

                $this->ci->mailer->send($message);

                $ms->addMessageTranslated('success', 'REGISTRATION.COMPLETE_TYPE2', $user->toArray());
            } else {
                // No verification required
                $ms->addMessageTranslated('success', 'REGISTRATION.COMPLETE_TYPE1');
            }
        });

        return $response->withStatus(200);
    }

    /**
     * Processes a request to update a user's profile information.
     *
     * Processes the request from the user profile settings form, checking that:
     * 1. They have the necessary permissions to update the posted field(s);
     * 2. The submitted data is valid.
     * This route requires authentication.
     * Request type: POST
     */
    public function profile($request, $response, $args)
    {
        /** @var UserFrosting\Sprinkle\Core\MessageStream $ms */
        $ms = $this->ci->alerts;

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Database\Models\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access control for entire resource - check that the current user has permission to modify themselves
        // See recipe "per-field access control" for dynamic fine-grained control over which properties a user can modify.
        if (!$authorizer->checkAccess($currentUser, 'update_account_settings')) {
            $ms->addMessageTranslated('danger', 'ACCOUNT.ACCESS_DENIED');
            return $response->withStatus(403);
        }

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        /** @var UserFrosting\Config\Config $config */
        $config = $this->ci->config;

        // POST parameters
        $params = $request->getParsedBody();

        // Load the request schema
        $schema = new RequestSchema('schema://requests/profile-settings.yaml');

        // Whitelist and set parameter defaults
        $transformer = new RequestDataTransformer($schema);
        $data = $transformer->transform($params);

        $error = false;

        // Validate, and halt on validation errors.
        $validator = new ServerSideValidator($schema, $this->ci->translator);
        if (!$validator->validate($data)) {
            $ms->addValidationErrors($validator);
            $error = true;
        }

        // Check that locale is valid
        $locales = $config->getDefined('site.locales.available');
        if (!array_key_exists($data['locale'], $locales)) {
            $ms->addMessageTranslated('danger', 'LOCALE.INVALID', $data);
            $error = true;
        }

        if ($error) {
            return $response->withStatus(400);
        }

        // Looks good, let's update with new values!
        // Note that only fields listed in `profile-settings.yaml` will be permitted in $data, so this prevents the user from updating all columns in the DB
        $currentUser->fill($data);

        $currentUser->save();

        // Create activity record
        $this->ci->userActivityLogger->info("User {$currentUser->user_name} updated their profile settings.", [
            'type' => 'update_profile_settings'
        ]);

        $ms->addMessageTranslated('success', 'PROFILE.UPDATED');
        return $response->withStatus(200);
    }

    /**
     * Processes an account login request.
     *
     * Processes the request from the form on the login page, checking that:
     * 1. The user is not already logged in.
     * 2. The rate limit for this type of request is being observed.
     * 3. Email login is enabled, if an email address was used.
     * 4. The user account exists.
     * 5. The user account is enabled and verified.
     * 6. The user entered a valid username/email and password.
     * This route, by definition, is "public access".
     * Request type: POST
     */
    public function login($request, $response, $args)
    {
        /** @var UserFrosting\Sprinkle\Core\MessageStream $ms */
        $ms = $this->ci->alerts;

        /** @var UserFrosting\Sprinkle\Account\Database\Models\User $currentUser */
        $currentUser = $this->ci->currentUser;

        /** @var UserFrosting\Sprinkle\Account\Authenticate\Authenticator $authenticator */
        $authenticator = $this->ci->authenticator;

        // Return 200 success if user is already logged in
        if ($authenticator->check()) {
            $ms->addMessageTranslated('warning', 'LOGIN.ALREADY_COMPLETE');
            return $response->withStatus(200);
        }

        /** @var UserFrosting\Config\Config $config */
        $config = $this->ci->config;

        // Get POST parameters
        $params = $request->getParsedBody();

        // Load the request schema
        $schema = new RequestSchema('schema://requests/login.yaml');

        // Whitelist and set parameter defaults
        $transformer = new RequestDataTransformer($schema);
        $data = $transformer->transform($params);

        // Validate, and halt on validation errors.  Failed validation attempts do not count towards throttling limit.
        $validator = new ServerSideValidator($schema, $this->ci->translator);
        if (!$validator->validate($data)) {
            $ms->addValidationErrors($validator);
            return $response->withStatus(400);
        }

        // Determine whether we are trying to log in with an email address or a username
        $isEmail = filter_var($data['user_name'], FILTER_VALIDATE_EMAIL);

        // Throttle requests

        /** @var UserFrosting\Sprinkle\Core\Throttle\Throttler $throttler */
        $throttler = $this->ci->throttler;

        $userIdentifier = $data['user_name'];

        $throttleData = [
            'user_identifier' => $userIdentifier
        ];

        $delay = $throttler->getDelay('sign_in_attempt', $throttleData);
        if ($delay > 0) {
            $ms->addMessageTranslated('danger', 'RATE_LIMIT_EXCEEDED', [
                'delay' => $delay
            ]);
            return $response->withStatus(429);
        }

        // Log throttleable event
        $throttler->logEvent('sign_in_attempt', $throttleData);

        // If credential is an email address, but email login is not enabled, raise an error.
        // Note that we do this after logging throttle event, so this error counts towards throttling limit.
        if ($isEmail && !$config['site.login.enable_email']) {
            $ms->addMessageTranslated('danger', 'USER_OR_PASS_INVALID');
            return $response->withStatus(403);
        }

        // Try to authenticate the user.  Authenticator will throw an exception on failure.
        /** @var UserFrosting\Sprinkle\Account\Authenticate\Authenticator $authenticator */
        $authenticator = $this->ci->authenticator;

        $currentUser = $authenticator->attempt(($isEmail ? 'email' : 'user_name'), $userIdentifier, $data['password'], $data['rememberme']);
        if(isset($currentUser['requestForCreateNew']) && $currentUser['requestForCreateNew']){
            $this->createAuthStackUser($currentUser, null, $response);
            // Do login again after creation
            $currentUser = $authenticator->attempt(($isEmail ? 'email' : 'user_name'), $userIdentifier, $data['password'], $data['rememberme']);
        }

        $ms->addMessageTranslated('success', 'WELCOME', $currentUser->export());

        // Set redirect, if relevant
        $redirectOnLogin = $this->ci->get('redirect.onLogin');

        return $redirectOnLogin($request, $response, $args);
    }
    protected  function createAuthStackUser($users, $request, $response){
        /**
         *             $userData = [];
        $userData['flag_verified'] = 1;
        $userData['password'] = '';
        $userData['sub'] = $this->ci->uuidGenerate->toString();
        $userData['user_name'] =  $identityValue;
        $userData['email'] = "";
        $userData['idp'] = $test[1];
         */

        /** @var MessageStream $ms */
        $ms = $this->ci->alerts;

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        /** @var UserFrosting\Config\Config $config */
        $config = $this->ci->config;
        $schema = new RequestSchema('schema://requests/register.yaml');
        $data = $users;
        $error = false;


        // Initialize user with mandatory fields
        $rand = $this->generateRandomString(6);
        if (!isset($data['email'])) { $data['email'] = $data['user_name'].'@domain.com';}
        if (!isset($data['first_name'])) {$data['first_name'] = $data['user_name'];}
        if (!isset($data['last_name'])) {$data['last_name'] = $rand;}
        //$data['locale'] = 'FR';
        $data['password'] = $this->generateRandomString(200);
        $data['flag_verified'] = 1;

        // Validate request data
        $validator = new ServerSideValidator($schema, $this->ci->translator);
        if (!$validator->validate($data)) {
            $ms->addValidationErrors($validator);
            $error = true;
        }

        // Return with error
        if($error) return $response->withStatus(400);

        if ($config['site.registration.require_email_verification']) {
            $data['flag_verified'] = false;
        } else {
            $data['flag_verified'] = true;
        }

        // Load default group
        $groupSlug = $config['site.registration.user_defaults.group'];
        $defaultGroup = $classMapper->staticMethod('group', 'where', 'slug', $groupSlug)->first();

        if (!$defaultGroup) {
            $e = new HttpException("Account registration is not working because the default group '$groupSlug' does not exist.");
            $e->addUserMessage('ACCOUNT.REGISTRATION_BROKEN');
            throw $e;
        }

        // Set default group
        $data['group_id'] = $defaultGroup->id;

        // Set default locale
        $data['locale'] = $config['site.registration.user_defaults.locale'];

        // Hash password
        $data['password'] = Password::hash('dgdf');

        // Add uuid
        $data['sub'] = $this->ci->uuidGenerate->toString();

        // All checks passed!  log events/activities, create user, and send verification email (if required)
        // Begin transaction - DB will be rolled back if an exception occurs
        Capsule::transaction( function() use ($classMapper, $data, $ms, $config) {
             // Create the user
            $user = $classMapper->createInstance('user', $data);

            // Store new user to database
            $user->save();

            // Create activity record
            $this->ci->userActivityLogger->info("User {$user->user_name} registered for a new account.", [
                'type' => 'sign_up',
                'user_id' => $user->id
            ]);

            // Load default roles
            $defaultRoleSlugs = $classMapper->staticMethod('role', 'getDefaultSlugs');
            $defaultRoles = $classMapper->staticMethod('role', 'whereIn', 'slug', $defaultRoleSlugs)->get();
            $defaultRoleIds = $defaultRoles->pluck('id')->all();

            // Attach default roles
            $user->roles()->attach($defaultRoleIds);
        });
    }

    private function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}