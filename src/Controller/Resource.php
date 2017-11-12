<?php
namespace UserFrosting\Sprinkle\OidcUser\Controller;

use UserFrosting\Fortress\RequestSchema;
use UserFrosting\Sprinkle\Core\Controller\SimpleController;

class Resource extends SimpleController{
    /**
     * This is called by the client app once the client has obtained an access
     * token for the current user.  If the token is valid, the resource (in this
     * case, the "friends" of the current user) will be returned to the client
     */
    public function resource($request, $response, $args)
    {
        // get the oauth server (configured in src/OAuth2Demo/Server/Server.php)
        $server = $this->ci->oauth_server;

        // get the oauth response (configured in src/OAuth2Demo/Server/Server.php)
        $response = $this->ci->oauth_response;

        if (!$server->verifyResourceRequest($request, $response)) {
            return $server->getResponse();
        } else {
            // return a fake API response - not that exciting
            // @TODO return something more valuable, like the name of the logged in user
            $api_response = array(
                'friends' => array(
                    'john',
                    'matt',
                    'jane'
                )
            );
            return new Response(json_encode($api_response));
        }
    }
}