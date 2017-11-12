<?php
namespace UserFrosting\Sprinkle\OidcUser\Oauth2\HttpFoundationBridge;

use OAuth2\ResponseInterface;
use Slim\Http\Response as BaseResponse;

class Response extends BaseResponse implements ResponseInterface{
    public function addParameters(array $parameters)
    {

    }

    public function addHttpHeaders(array $httpHeaders)
    {

    }

    public function setStatusCode($statusCode)
    {

    }

    public function setError($statusCode, $name, $description = null, $uri = null)
    {

    }

    public function setRedirect($statusCode, $url, $state = null, $error = null, $errorDescription = null, $errorUri = null)
    {

    }

    public function getParameter($name)
    {

    }
}