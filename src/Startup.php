<?php

namespace Clover\Nano;

use ReflectionClass;
use ReflectionException;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Clover\Nano\Core\App;
use Clover\Nano\Core\Common;
use Composer\Autoload\ClassLoader;

/**
 * Class Startup
 * @package Clover\Nano
 */
class Startup
{
    private $name = 'nano';
    private $env = 'develop';
    private $debug = true;

    /**
     * Boot constructor.
     * @param string $name
     * @param string $env
     * @param bool $debug
     * @throws ReflectionException
     */
    public function __construct($name = 'nano', $env = 'develop', $debug = true)
    {
        //定义基本常量
        $this->env = $env;
        $this->name = $name;
        $this->debug = $debug;
        define('APP_ENV', $this->env, true);
        define('APP_NAME', $this->name, true);
        define('APP_DEBUG', $this->debug, true);

        //定义项目目录
        if (!defined('APP_PATH')) {
            $reflection = new ReflectionClass(ClassLoader::class);
            $appPath = explode('/', str_replace('\\', '/', dirname(dirname($reflection->getFileName()))));
            array_pop($appPath);
            $appPath = implode("/", $appPath) . '/';
            define('APP_PATH', $appPath, true);
        }

        //设置错误处理
        error_reporting(APP_DEBUG ? E_ALL : 0);
        ini_set('display_errors', APP_DEBUG ? 'On' : 'Off');
        ini_set('display_startup_errors', APP_DEBUG ? 'On' : 'Off');

        //设置默认时区
        $timezone = getenv('timezone');
        date_default_timezone_set($timezone ? $timezone : 'Asia/Shanghai');
        Common::initial($timezone);
    }
}