<?php
namespace UserFrosting\Sprinkle\OidcUser\Oauth2;

use OAuth2\Storage\Pdo;
use OAuth2\Storage\UserCredentialsInterface;
use UserFrosting\Sprinkle\Account\Authenticate\Authenticator;

class OauthStorePdo extends Pdo{
    protected  $userCredentialService;
    protected  $userClaimsService;

    public function __construct($connection, $config = array())
    {
        $this->db = $connection;
        // debugging
        $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->config = array_merge(array(
            'client_table' => 'oauth_clients',
            'access_token_table' => 'oauth_access_tokens',
            'refresh_token_table' => 'oauth_refresh_tokens',
            'code_table' => 'oauth_authorization_codes',
            // todo
            'user_table' => 'oauth_users',
            'jwt_table'  => 'oauth_jwt',
            'jti_table'  => 'oauth_jti',
            'scope_table'  => 'oauth_scopes',
            'public_key_table'  => 'oauth_public_keys',
        ), $config);
    }

    public function setUserCredentialService(UserCredentialsInterface $service){
        $this->userCredentialService = $service;
    }

    /* OAuth2\Storage\UserCredentialsInterface */
    public function checkUserCredentials($username, $password)
    {
        if($this->userCredentialService instanceof UserCredential){
            if($this->userCredentialService->authenticator instanceof Authenticator){
                return $this->userCredentialService->authenticator->oidcAttempt($username, $password);
            }
        }
        return false;
    }

    public function getUserDetails($username)
    {
        if($this->userCredentialService instanceof UserCredential){
            return $this->userCredentialService->getUserDetails($username);
        }
        return false;
    }

    /* UserClaimsInterface */
    public function getUserClaims($user_id, $claims)
    {
        if (!$userDetails = $this->getUserDetails($user_id)) {
            return false;
        }

        $claims = explode(' ', trim($claims));
        $userClaims = array();

        // for each requested claim, if the user has the claim, set it in the response
        $validClaims = explode(' ', self::VALID_CLAIMS);
        foreach ($validClaims as $validClaim) {
            if (in_array($validClaim, $claims)) {
                if ($validClaim == 'address') {
                    // address is an object with subfields
                    $userClaims['address'] = $this->getUserClaim($validClaim, $userDetails['address'] ?: $userDetails);
                } else {
                    $userClaims = array_merge($userClaims, $this->getUserClaim($validClaim, $userDetails));
                }
            }
        }

        return $userClaims;
    }

    protected function getUserClaim($claim, $userDetails)
    {
        $userClaims = array();
        $claimValuesString = constant(sprintf('self::%s_CLAIM_VALUES', strtoupper($claim)));
        $claimValues = explode(' ', $claimValuesString);

        foreach ($claimValues as $value) {
            $userClaims[$value] = isset($userDetails[$value]) ? $userDetails[$value] : null;
        }

        return $userClaims;
    }
}