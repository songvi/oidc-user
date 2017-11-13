<?php
namespace UserFrosting\Sprinkle\OidcUser\Oauth2;

use Chadicus\Slim\OAuth2\Http\RequestBridge;
use Chadicus\Slim\OAuth2\Http\ResponseBridge;
use OAuth2\Response;
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

        $oauth2Response = new Response();
        $request = RequestBridge::toOAuth2($request);

        // let the oauth2-server-php library do all the work!
        $oauth2Response =  $server->handleTokenRequest($request, $oauth2Response);
        return ResponseBridge::fromOauth2($oauth2Response);
    }
}