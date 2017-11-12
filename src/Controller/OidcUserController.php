<?php
namespace UserFrosting\Sprinkle\OidcUser\Controller;

use Illuminate\Database\Capsule\Manager as Capsule;
use UserFrosting\Fortress\RequestDataTransformer;
use UserFrosting\Fortress\RequestSchema;
use UserFrosting\Fortress\ServerSideValidator;
use UserFrosting\Sprinkle\Admin\Controller\UserController;
use UserFrosting\Sprinkle\Core\Mail\EmailRecipient;
use UserFrosting\Sprinkle\Core\Mail\TwigMailMessage;
use UserFrosting\Support\Exception\ForbiddenException;

class OidcUserController extends UserController
{
    /**
     * Renders a page displaying a user's information, in read-only mode.
     *
     * This checks that the currently logged-in user has permission to view the requested user's info.
     * It checks each field individually, showing only those that you have permission to view.
     * This will also try to show buttons for activating, disabling/enabling, deleting, and editing the user.
     * This page requires authentication.
     * Request type: GET
     */
    public function pageInfo($request, $response, $args)
    {
        $user = $this->getUserFromParams($args);

        // If the user no longer exists, forward to main user listing page
        if (!$user) {
            $usersPage = $this->ci->router->pathFor('uri_users');
            return $response->withRedirect($usersPage, 404);
        }

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager $authorizer */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Database\Models\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled page
        if (!$authorizer->checkAccess($currentUser, 'uri_user', [
            'user' => $user
        ])) {
            throw new ForbiddenException();
        }

        /** @var UserFrosting\Config\Config $config */
        $config = $this->ci->config;

        // Get a list of all locales
        $locales = $config->getDefined('site.locales.available');

        // Determine fields that currentUser is authorized to view
        $fieldNames = ['user_name', 'name', 'email', 'locale', 'group', 'roles', 'address', 'gender', 'zoneinfo'];

        // Generate form
        $fields = [
            // Always hide these
            'hidden' => ['theme']
        ];

        // Determine which fields should be hidden
        foreach ($fieldNames as $field) {
            if (!$authorizer->checkAccess($currentUser, 'view_user_field', [
                'user' => $user,
                'property' => $field
            ])) {
                $fields['hidden'][] = $field;
            }
        }

        // Determine buttons to display
        $editButtons = [
            'hidden' => []
        ];

        if (!$authorizer->checkAccess($currentUser, 'update_user_field', [
            'user' => $user,
            'fields' => ['name', 'email', 'locale']
        ])) {
            $editButtons['hidden'][] = 'edit';
        }

        if (!$authorizer->checkAccess($currentUser, 'update_user_field', [
            'user' => $user,
            'fields' => ['flag_enabled']
        ])) {
            $editButtons['hidden'][] = 'enable';
        }

        if (!$authorizer->checkAccess($currentUser, 'update_user_field', [
            'user' => $user,
            'fields' => ['flag_verified']
        ])) {
            $editButtons['hidden'][] = 'activate';
        }

        if (!$authorizer->checkAccess($currentUser, 'update_user_field', [
            'user' => $user,
            'fields' => ['password']
        ])) {
            $editButtons['hidden'][] = 'password';
        }

        if (!$authorizer->checkAccess($currentUser, 'update_user_field', [
            'user' => $user,
            'fields' => ['roles']
        ])) {
            $editButtons['hidden'][] = 'roles';
        }

        if (!$authorizer->checkAccess($currentUser, 'delete_user', [
            'user' => $user
        ])) {
            $editButtons['hidden'][] = 'delete';
        }

        return $this->ci->view->render($response, 'pages/user.html.twig', [
            'user' => $user,
            'locales' => $locales,
            'fields' => $fields,
            'tools' => $editButtons
        ]);
    }

    /**
     * Processes the request to update an existing user's basic details (first_name, last_name, email, locale, group_id)
     *
     * Processes the request from the user update form, checking that:
     * 1. The target user's new email address, if specified, is not already in use;
     * 2. The logged-in user has the necessary permissions to update the putted field(s);
     * 3. The submitted data is valid.
     * This route requires authentication.
     * Request type: PUT
     */
    public function updateInfo($request, $response, $args)
    {
        // Get the username from the URL
        $user = $this->getUserFromParams($args);

        if (!$user) {
            throw new NotFoundException($request, $response);
        }

        /** @var UserFrosting\Config\Config $config */
        $config = $this->ci->config;

        // Get PUT parameters
        $params = $request->getParsedBody();

        /** @var UserFrosting\Sprinkle\Core\MessageStream $ms */
        $ms = $this->ci->alerts;

        // Load the request schema
        $schema = new RequestSchema('schema://requests/user/edit-info.yaml');

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

        // Determine targeted fields
        $fieldNames = [];
        foreach ($data as $name => $value) {
            if ($name == 'first_name' || $name == 'last_name') {
                $fieldNames[] = 'name';
            } elseif ($name == 'group_id') {
                $fieldNames[] = 'group';
            } else {
                $fieldNames[] = $name;
            }
        }

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager $authorizer */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Database\Models\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled resource - check that currentUser has permission to edit submitted fields for this user
        if (!$authorizer->checkAccess($currentUser, 'update_user_field', [
            'user' => $user,
            'fields' => array_values(array_unique($fieldNames))
        ])) {
            throw new ForbiddenException();
        }

        // Only the master account can edit the master account!
        if (
            ($user->id == $config['reserved_user_ids.master']) &&
            ($currentUser->id != $config['reserved_user_ids.master'])
        ) {
            throw new ForbiddenException();
        }

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        // Check if email already exists
        if (
            isset($data['email']) &&
            $data['email'] != $user->email &&
            $classMapper->staticMethod('user', 'findUnique', $data['email'], 'email')
        ) {
            $ms->addMessageTranslated('danger', 'EMAIL.IN_USE', $data);
            $error = true;
        }

        if ($error) {
            return $response->withStatus(400);
        }

        // Begin transaction - DB will be rolled back if an exception occurs
        Capsule::transaction( function() use ($data, $user, $currentUser) {
            // Update the user and generate success messages
            foreach ($data as $name => $value) {
                if ($value != $user->$name) {
                    $user->$name = $value;
                }
            }

            $user->save();

            // Create activity record
            $this->ci->userActivityLogger->info("User {$currentUser->user_name} updated basic account info for user {$user->user_name}.", [
                'type' => 'account_update_info',
                'user_id' => $currentUser->id
            ]);
        });

        $ms->addMessageTranslated('success', 'DETAILS_UPDATED', [
            'user_name' => $user->user_name
        ]);
        return $response->withStatus(200);
    }

    /**
     * Processes the request to create a new user (from the admin controls).
     *
     * Processes the request from the user creation form, checking that:
     * 1. The username and email are not already in use;
     * 2. The logged-in user has the necessary permissions to update the posted field(s);
     * 3. The submitted data is valid.
     * This route requires authentication.
     * Request type: POST
     * @see getModalCreate
     */
    public function create($request, $response, $args)
    {
        // Get POST parameters: user_name, first_name, last_name, email, locale, (group)
        $params = $request->getParsedBody();

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager $authorizer */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Database\Models\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled page
        if (!$authorizer->checkAccess($currentUser, 'create_user')) {
            throw new ForbiddenException();
        }

        /** @var UserFrosting\Sprinkle\Core\MessageStream $ms */
        $ms = $this->ci->alerts;

        // Load the request schema
        $schema = new RequestSchema('schema://requests/user/create.yaml');

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

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        // Check if username or email already exists
        if ($classMapper->staticMethod('user', 'findUnique', $data['user_name'], 'user_name')) {
            $ms->addMessageTranslated('danger', 'USERNAME.IN_USE', $data);
            $error = true;
        }

        if ($classMapper->staticMethod('user', 'findUnique', $data['email'], 'email')) {
            $ms->addMessageTranslated('danger', 'EMAIL.IN_USE', $data);
            $error = true;
        }

        if ($error) {
            return $response->withStatus(400);
        }

        /** @var UserFrosting\Config\Config $config */
        $config = $this->ci->config;

        // If currentUser does not have permission to set the group, but they try to set it to something other than their own group,
        // throw an exception.
        if (!$authorizer->checkAccess($currentUser, 'create_user_field', [
            'fields' => ['group']
        ])) {
            if (isset($data['group_id']) && $data['group_id'] != $currentUser->group_id) {
                throw new ForbiddenException();
            }
        }

        // In any case, set the group id if not otherwise set
        if (!isset($data['group_id'])) {
            $data['group_id'] = $currentUser->group_id;
        }

        $data['flag_verified'] = 1;
        // Set password as empty on initial creation.  We will then send email so new user can set it themselves via a verification token
        $data['password'] = '';

        // Add uuid
        $data['sub'] = $this->ci->uuidGenerate->toString();

        // All checks passed!  log events/activities, create user, and send verification email (if required)
        // Begin transaction - DB will be rolled back if an exception occurs
        Capsule::transaction( function() use ($classMapper, $data, $ms, $config, $currentUser) {
            // Create the user
            $user = $classMapper->createInstance('user', $data);

            // Store new user to database
            $user->save();

            // Create activity record
            $this->ci->userActivityLogger->info("User {$currentUser->user_name} created a new account for {$user->user_name}.", [
                'type' => 'account_create',
                'user_id' => $currentUser->id
            ]);

            // Load default roles
            $defaultRoleSlugs = $classMapper->staticMethod('role', 'getDefaultSlugs');
            $defaultRoles = $classMapper->staticMethod('role', 'whereIn', 'slug', $defaultRoleSlugs)->get();
            $defaultRoleIds = $defaultRoles->pluck('id')->all();

            // Attach default roles
            $user->roles()->attach($defaultRoleIds);

            // Try to generate a new password request
            $passwordRequest = $this->ci->repoPasswordReset->create($user, $config['password_reset.timeouts.create']);

            // Create and send welcome email with password set link
            $message = new TwigMailMessage($this->ci->view, 'mail/password-create.html.twig');

            $message->from($config['address_book.admin'])
                ->addEmailRecipient(new EmailRecipient($user->email, $user->full_name))
                ->addParams([
                    'user' => $user,
                    'create_password_expiration' => $config['password_reset.timeouts.create'] / 3600 . ' hours',
                    'token' => $passwordRequest->getToken()
                ]);

            //$this->ci->mailer->send($message);

            $ms->addMessageTranslated('success', 'USER.CREATED', $data);
        });

        return $response->withStatus(200);
    }
}
