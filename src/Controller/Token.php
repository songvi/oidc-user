<?php
namespace UserFrosting\Sprinkle\OidcUser\Controller;

use UserFrosting\Fortress\RequestSchema;
use UserFrosting\Sprinkle\Core\Controller\SimpleController;

class Token extends SimpleController{
    /**
     * This is called by the client app once the client has obtained
     * an authorization code from the Authorize Controller (@see OAuth2Demo\Server\Controllers\Authorize).
     * If the request is valid, an access token will be returned
     */
    public function token($request, $response, $args)
    {
        // get the oauth server (configured in src/OAuth2Demo/Server/Server.php)
        $server = $this->ci->oauth_server;

        // get the oauth response (configured in src/OAuth2Demo/Server/Server.php)
        $response = $this->ci->oauth_response;

        // let the oauth2-server-php library do all the work!
        return $server->handleTokenRequest($request, $response);
    }
}