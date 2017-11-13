<?php
namespace UserFrosting\Sprinkle\OidcUser\ServicesProvider;

use OAuth2\GrantType\RefreshToken;
use OAuth2\GrantType\UserCredentials;
use OAuth2\OpenID\GrantType\AuthorizationCode;
use Ramsey\Uuid\Uuid;
use UserFrosting\Sprinkle\OidcUser\Authenticate\OidcAuthenticator;
use Illuminate\Database\Capsule\Manager as Capsule;
use UserFrosting\Sprinkle\OidcUser\Oauth2\OauthStorePdo;
use OAuth2\Server as OAuth2Server;
use OAuth2\Storage\Memory;

class ServicesProvider
{
    /**
     * Register extended user fields services.
     *
     * @param Container $container A DI container implementing ArrayAccess and container-interop.
     */
    public function register($container)
    {
        /**
         * Extend the 'classMapper' service to register model classes.
         *
         * Mappings added: Member
         */
        $container->extend('classMapper', function ($classMapper, $c) {
            $classMapper->setClassMapping('user', 'UserFrosting\Sprinkle\OidcUser\Database\Models\OidcUser');
            return $classMapper;
        });

        $container['uuidGenerate'] = function ($c) {
            return Uuid::uuid4();
        };

        /**
         * Authentication service.
         *
         * Supports logging in users, remembering their sessions, etc.
         */
        $container['authenticator'] = function ($c) {
            $classMapper = $c->classMapper;
            $config = $c->config;
            $session = $c->session;
            $cache = $c->cache;

            // Force database connection to boot up
            $c->db;

            // Fix RememberMe table name
            $config['remember_me.table.tableName'] = Capsule::connection()->getTablePrefix() . $config['remember_me.table.tableName'];

            $authenticator = new OidcAuthenticator($classMapper, $session, $config, $cache);
            return $authenticator;
        };

        /**
         * OIDC OAUTH service
         *
         */

        $container['oauth_server'] = function($c){
            $config = $c->config;
            $c->db;
            $connection = Capsule::connection()->getPdo();
            $storage = new OauthStorePdo($connection, []);

            // create array of supported grant types
            $grantTypes = array(
                'authorization_code' => new AuthorizationCode($storage),
                'user_credentials'   => new UserCredentials($storage),
                'refresh_token'      => new RefreshToken($storage, array(
                    'always_issue_new_refresh_token' => true,
                )),
            );

            // instantiate the oauth server
            $server = new Oauth2Server($storage, array(
                'enforce_state' => true,
                'allow_implicit' => true,
                'use_openid_connect' => true,
                'issuer' => $_SERVER['HTTP_HOST'],
            ),$grantTypes);

            $server->addStorage($this->getKeyStorage(), 'public_key');

            return $server;
        };
    }

    private function getKeyStorage()
    {
        $publicKey  = file_get_contents($this->getModuleRoot().'/config/pubkey.pem');
        $privateKey = file_get_contents($this->getModuleRoot().'/config/privkey.pem');

        // create storage
        $keyStorage = new Memory(array('keys' => array(
            'public_key'  => $publicKey,
            'private_key' => $privateKey,
        )));

        return $keyStorage;
    }

    private function getModuleRoot()
    {
        return dirname(__DIR__);
    }
}
