<?php
namespace UserFrosting\Sprinkle\OidcUser\Oauth2;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;



class MessageConverter {

    public static function getPsr7Request(Request $symfonyRequest){
        $psr7Factory = new DiactorosFactory();
        return $psr7Factory->createRequest($symfonyRequest);
    }

    public static function getPsr7Response(Response $symfonyResponse){
        $psr7Factory = new DiactorosFactory();
        return $psr7Factory->createResponse($symfonyResponse);
    }

    public static function getSymfonyRequest(RequestInterface $psr7Request){
        $httpFoundationFactory = new HttpFoundationFactory();
        return $httpFoundationFactory->createRequest($psr7Request);
    }

    public static function getSymfonyResponse(ResponseInterface $psr7Response){
        $httpFoundationFactory = new HttpFoundationFactory();
        return $httpFoundationFactory->createRequest($psr7Response);
    }
}
