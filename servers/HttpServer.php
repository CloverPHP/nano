<?php


namespace Server;


use Clover\Nano\Bootstrap;
use Clover\Nano\Core\Common;
use Simps\Context;
use Simps\Listener;
use Simps\Route;
use Simps\Server\Http;
use Swoole\Timer;
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
            $boot = new Bootstrap();
            $boot->__invoke($request, $response);
        } catch (Throwable $th) {
            echo $th->getMessage();
            $response->status(500);
            $response->end('');
        }
        unset($boot);
    }

    public function onWorkerStart(\Swoole\Server $server, int $workerId)
    {
        Timer::tick(5000, function () {
            gc_collect_cycles();
            echo sprintf("pid=%d,mem=%s\n", getmypid(), Common::fileSize2Unit(memory_get_usage()));
        });
    }
}