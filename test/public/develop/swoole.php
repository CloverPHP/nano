<?php

use Swoole\Runtime;
use Simps\Application;
use Clover\Nano\Startup;

if (php_sapi_name() !== 'cli')
    return;

require __DIR__ . '/../../vendor/autoload.php';

$boot = new Startup('nano', 'develop', true);

define('IN_SWOOLE', true, true);
define('CONFIG_PATH', APP_PATH . 'configs/server/', true);
Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
Application::run();
