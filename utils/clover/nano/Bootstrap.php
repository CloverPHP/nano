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
 * Class Boot
 * @package Clover\Nano
 */
class Bootstrap
{
    private $name = 'nano';
    private $env = 'develop';
    private $debug = true;
    private $header = [];
    private $params = [];
    private $cookie = [];
    private $server = [];

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

    /**
     * 处理一个请求
     * @param Request|null $request
     * @param Response|null $response
     */
    public function __invoke(Request $request = null, Response $response = null)
    {
        if (defined('IN_SWOOLE'))
            $this->parseSwoole($request, $response);
        elseif (php_sapi_name() !== 'cli')
            $this->parseCgi();
        else
            $this->parseCmd();

        //
        $this->prepareRequest();
        $app = new App($this->header, $this->params, $this->cookie, $this->server, $request, $response);
        $output = $app->__invoke();
        if (defined('IN_SWOOLE')) {
            if ($header = $app->response->getHeader()) {
                foreach ($header as $k => $v)
                    $response->header($k, $v);
            }
            $response->end($output);
        }
    }


    /**
     * 解析cgi/fpm的参数
     */
    final private function parseCgi()
    {
        foreach ($_COOKIE as $key => $value)
            $this->cookie[$key] = $value;
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'http_')
                $this->header[strtolower(str_replace("http_", "", $key))] = $value;
            else
                $this->server[$key] = $value;
        }
        if (!isset($this->server['path_info']))
            $this->server['path_info'] = isset($this->server['request_uri']) ? ['request_uri'] : '';
        $mime = isset($this->server['content_type']) ? $this->server['content_type'] : '';
        $this->params = stristr($mime, 'json') === false ? array_replace($_GET, $_POST, $_FILES)
            : (array)json_decode(file_get_contents('php://input'), true);
    }

    /**
     * 解析传统命令行的参数
     */
    final private function parseCmd()
    {
        //
        foreach ($_SERVER as $key => $value)
            $this->server[$key] = $value;

        //
        $opts = getopt('r::', ['run:']);
        $run = parse_url($opts['run']);
        if (preg_match("/^([a-z][a-z0-9]*)\/([a-z][a-z0-9]*)(\?.*)?$/i", trim($run['path'], "\t\n\r\0\x0B/ "), $matches)) {
            $this->header['service'] = $matches[1];
            $this->header['action'] = $matches[2];
            $this->server['path_info'] = $run['path'];
            if (!empty($run['query']))
                parse_str($run['query'], $this->params);
        } else {
            fwrite(STDOUT, "example:/usr/bin/php path/to/index.php --run=/path/to/service?query_params" . PHP_EOL);
            exit(0);
        }
    }

    /**
     * 解析swoole请求的参数
     * @param Request $request
     * @param Response $response
     */
    final private function parseSwoole(Request $request, Response $response)
    {
        $this->header = &$request->header;
        $this->server = &$request->server;
        $this->cookie = &$request->cookie;
        $mime = isset($this->server['content_type']) ? $this->server['content_type'] : '';
        $this->params = stristr($mime, 'json') === false ? array_replace($request->get, $request->post, $request->files)
            : (array)json_decode($request->rawContent(), true);
    }

    /**
     * 准备好nano特有的参数
     */
    final private function prepareRequest()
    {
        $this->header['service'] = $this->header['service'] ? $this->header['service'] : '';
        if (empty($this->header['service'])) {
            if ($this->server['path_info']) {
                $parts = explode("/", trim($this->server['path_info'], "\t\r\n\0\x0B/"), 2);
                $this->header['service'] = array_shift($parts);
                if ($parts) $this->header['action'] = array_shift($parts);
            } else
                $this->header['service'] = 'Index';
        }
        $this->header['action'] = $this->header['action'] ? $this->header['action'] : '';

        //
        if (!empty($this->server['php_auth_user']))
            $this->header['username'] = (string)$this->server['php_auth_user'];
        if (!empty($this->server['php_auth_pw']))
            $this->header['password'] = (string)$this->server['php_auth_pw'];

    }
}