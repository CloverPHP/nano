<?php

declare(strict_types=1);

namespace Server;

use Clover\Nano\Core\Common;
use Simps\Listener;
use Swoole\Server;
use Swoole\Timer;
use Exception;

/**
 * Class WebSocket
 */
class WebSocket extends \Simps\Server\WebSocket
{

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