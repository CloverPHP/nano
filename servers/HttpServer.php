<?php

declare(strict_types=1);

namespace Server;

use Clover\Nano\Bootstrap;
use Clover\Nano\Core\Common;
use Simps\Context;
use Simps\Listener;
use Simps\Server\Http;
use Swoole\Server;
use Swoole\Timer;
use Throwable;
use Exception;

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

    /**
     * @param Server $server
     * @param int $workerId
     * @throws Exception
     */
    public function onWorkerStart(Server $server, int $workerId)
    {
        Listener::getInstance()->listen('workerStart', $server, $workerId);

        Timer::tick(60000, function () {
            gc_collect_cycles();
            echo sprintf("pid=%d,mem=%s\n", getmypid(), Common::fileSize2Unit(memory_get_usage()));
        });
    }
}