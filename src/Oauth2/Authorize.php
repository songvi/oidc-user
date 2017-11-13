<?php
namespace UserFrosting\Sprinkle\OidcUser\Oauth2;

use Chadicus\Slim\OAuth2\Http\RequestBridge;
use Chadicus\Slim\OAuth2\Http\ResponseBridge;
use OAuth2\Response;
use UserFrosting\Fortress\RequestSchema;
use UserFrosting\Sprinkle\Core\Controller\SimpleController;
use UserFrosting\Sprinkle\OidcUser\Oauth2\MessageConverter;

class Authorize extends SimpleController{
    /**
     * The user is directed here by the client in order to authorize the client app
     * to access his/her data
     */
    public function authorize($request, $response, $args)
    {
        // get the oauth server (configured in src/OAuth2Demo/Server/Server.php)
        $server = $this->ci->oauth_server;
        $oauth2Response = new Response();
        $request = RequestBridge::toOAuth2($request);


        // validate the authorize request.  if it is invalid, redirect back to the client with the errors in tow
        if (!$server->validateAuthorizeRequest($request, $oauth2Response)) {
            $response = ResponseBridge::fromOauth2($server->getResponse());
            return $response;
        }

        // display the "do you want to authorize?" form
        // TODO
        /**
        return $app['twig']->render('server/authorize.twig', array(
            'client_id' => $app['request']->query->get('client_id'),
            'response_type' => $app['request']->query->get('response_type')
        ));
         * */
    }

    /**
     * This is called once the user decides to authorize or cancel the client app's
     * authorization request
     */
    public function authorizeFormSubmit($request, $response, $args)
    {
        // get the oauth server (configured in src/OAuth2Demo/Server/Server.php)
        $server = $this->ci->oauth_server;

        // get the oauth response (configured in src/OAuth2Demo/Server/Server.php)
        $response = $this->ci->oauth_response;

        //$request = \UserFrosting\Sprinkle\OidcUser\Oauth2\HttpFoundationBridge\Request::createFromGlobals()
        //$server->handleAuthorizeRequest($request, $response, $authorized);
    }
}