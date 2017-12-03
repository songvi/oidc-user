<?php

namespace UserFrosting\Sprinkle\OidcUser\Oauth2;

use OAuth2\Storage\UserCredentialsInterface;
use UserFrosting\Sprinkle\OidcUser\Authenticate\OidcAuthenticator;

class UserCredential implements  UserCredentialsInterface{
    public $authenticator;

    public function __construct($authenticator){
        $this->authenticator = $authenticator;
    }
    public function checkUserCredentials($username, $password)
    {
        if ($this->authenticator instanceof OidcAuthenticator){
            return $this->authenticator->oidcAttempt($username, $password);
        }
        return false;
    }

    public function getUserDetails($username)
    {
        if ($this->authenticator instanceof OidcAuthenticator){
            return $this->authenticator->oidcGetUser($username);
        }
        return false;
    }
}
