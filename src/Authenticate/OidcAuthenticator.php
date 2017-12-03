<?php
namespace UserFrosting\Sprinkle\OidcUser\Authenticate;
use AuthStack\AuthStack;
use AuthStack\Services\ConfigService;
use UserFrosting\Session\Session;
use UserFrosting\Sprinkle\Account\Authenticate\Authenticator;
use UserFrosting\Sprinkle\Account\Authenticate\Exception\AccountDisabledException;
use UserFrosting\Sprinkle\Account\Authenticate\Exception\AccountNotVerifiedException;
use UserFrosting\Sprinkle\Account\Authenticate\Exception\InvalidCredentialsException;
use UserFrosting\Sprinkle\Account\Util\Password;
use UserFrosting\Sprinkle\Core\Util\ClassMapper;
use Illuminate\Database\Capsule\Manager as Capsule;
//user UserFrosting\Sprinkle\OidcUser\Authenticate\ClassMapper;

/**
 * Handles authentication tasks.
 *
 * @author Alex Weissman (https://alexanderweissman.com)
 * Partially inspired by Laravel's Authentication component: https://github.com/laravel/framework/blob/5.3/src/Illuminate/Auth/SessionGuard.php
 */
class OidcAuthenticator extends Authenticator
{
    protected  $aStack;
    /**
     * @Override attempt of Authenticator
     * Attempts to authenticate a user based on a supplied identity and password.
     *
     * If successful, the user's id is stored in session.
     */

    public function __construct(ClassMapper $classMapper, Session $session, $config, $cache){
        // call parent constructor
        parent::__construct($classMapper, $session, $config, $cache);

        // Initialize auth stack
        $filePath = __DIR__."/../../config/auth_stack.yaml";
        $confService = new ConfigService();
        $confService->init($filePath);
        $stack = $confService->getAuthStack();
        $logger = $confService->getLogger();
        $this->aStack = new AuthStack($stack, $logger);
    }

    public function oidcAttempt($username, $password){
        $test = $this->externalCheckPassword($username, $password);
        if (is_array($test) && isset($test[0]) && $test[0]){
            return true;
        }
        return false;
    }

    public function oidcGetUser($username){
        $identityColumn = 'user_name';
        $user = $this->classMapper->staticMethod('user', 'where', $identityColumn, $username)->first();
        if ($user) {
            $arrayUser = $user->attributesToArray();
            if(isset($arrayUser['id'])) {
                unset($arrayUser['id']);
            }
            if(isset($arrayUser['flag_verified'])) {
                unset($arrayUser['flag_verified']);
            }
            if(isset($arrayUser['flag_enabled'])) {
                unset($arrayUser['flag_enabled']);
            }
            if(isset($arrayUser['deleted_at'])) {
                unset($arrayUser['deleted_at']);
            }

            $arrayUser['user_id'] = $arrayUser['sub'];
            return $arrayUser;
        }
        return null;
    }

    public function attempt($identityColumn, $identityValue, $password, $rememberMe = false)
    {
        $test = $this->externalCheckPassword($identityValue, $password);
        // Try to load the user, using the specified conditions
        $user = $this->classMapper->staticMethod('user', 'where', $identityColumn, $identityValue)->first();

        // If user does not exist => create new

        if (!$user && is_array($test) && $test[0] && $test[1]){
            // Create user in
            $userData = [];
            $userData['flag_verified'] = 1;
            $userData['password'] = '';
            $userData['user_name'] =  $identityValue;
            $userData['email'] = "";
            $userData['idp'] = $test[1];
            $userData['requestForCreateNew'] = true;
            return $userData;
        }

        if($user && $test[0]){
            $this->login($user, $rememberMe);
            return $user;
        }

        $user = $this->classMapper->staticMethod('user', 'where', $identityColumn, $identityValue)->first();

        if (!$user) {
            throw new InvalidCredentialsException();
        }

        // Check that the user has a password set (so, rule out newly created accounts without a password)
        if (!$user->password) {
            throw new InvalidCredentialsException();
        }

        // Check that the user's account is enabled
        if ($user->flag_enabled == 0) {
            throw new AccountDisabledException();
        }

        // Check that the user's account is activated
        if ($user->flag_verified == 0) {
            throw new AccountNotVerifiedException();
        }

        // Here is my password.  May I please assume the identify of this user now?

        if (Password::verify($password, $user->password)) {
            $this->login($user, $rememberMe);
            return $user;
        } else {
            // We know the password is at fault here (as opposed to the identity), but lets not give away the combination in case of someone bruteforcing
            throw new InvalidCredentialsException();
        }
    }

    protected function externalCheckPassword($username,$password){
        try{
            $test = $this->aStack->localCheckPassword($username, $password);
            return $test;
        }
        catch (\Exception $e){
            return false;
        }
    }
}
