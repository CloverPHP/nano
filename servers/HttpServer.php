<?php


namespace Server;


use Simps\Context;
use Simps\Server\Http;
use Throwable;

/**
 * Class HttpServer
 * Enhanced curl and make more easy to use
 */
class HttpServer extends Http
{
    public function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        Context::set('SwRequest', $request);
        Context::set('SwResponse', $response);

        try {
            global $boot;
            $boot->__invoke($request, $response);
        } catch (Throwable $th) {
            echo $th->getMessage();
            $response->status(500);
            $response->end('');
        }
    }
}