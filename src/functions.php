<?php

declare(strict_types=1);

use Clover\Nano\EnvConfig;

if (!function_exists('env_config')) {
    function env_config($name, $default = null)
    {
        return EnvConfig::getInstance()->get($name, $default);
    }
}