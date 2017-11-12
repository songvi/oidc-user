<?php
namespace UserFrosting\Sprinkle\OidcUser\Authenticate;
use       UserFrosting\Sprinkle\Account\Authenticate\Authenticator;
use UserFrosting\Sprinkle\Account\Authenticate\Exception\AccountDisabledException;
use UserFrosting\Sprinkle\Account\Authenticate\Exception\AccountNotVerifiedException;
use UserFrosting\Sprinkle\Account\Authenticate\Exception\InvalidCredentialsException;
use UserFrosting\Sprinkle\Account\Util\Password;

/**
 * Handles authentication tasks.
 *
 * @author Alex Weissman (https://alexanderweissman.com)
 * Partially inspired by Laravel's Authentication component: https://github.com/laravel/framework/blob/5.3/src/Illuminate/Auth/SessionGuard.php
 */
class OidcAuthenticator extends Authenticator
{
    /**
     * @Override attempt of Authenticator
     * Attempts to authenticate a user based on a supplied identity and password.
     *
     * If successful, the user's id is stored in session.
     */

    public function attempt($identityColumn, $identityValue, $password, $rememberMe = false)
    {
        // Try to load the user, using the specified conditions
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

}
