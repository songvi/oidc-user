<?php
namespace UserFrosting\Sprinkle\OidcUser\Oauth2\HttpFoundationBridge;

use OAuth2\RequestInterface;
use Slim\Http\Request as BaseRequest;

class Request extends BaseRequest implements RequestInterface{

    public function query($name, $default = null)
    {

    }

    public function request($name, $default = null)
    {

    }

    public function server($name, $default = null)
    {

    }

    public function headers($name, $default = null)
    {

    }

    public function getAllQueryParameters()
    {

    }
}