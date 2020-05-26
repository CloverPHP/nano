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
class Boot
{
    private $name = 'nano';
    private $env = 'production';
    private $debug = false;
    private $headers = [];
    private $params = [];
    private $cookie = [];
    private $server = [];

    /**
     * Boot constructor.
     * @param array $params
     * @throws ReflectionException
     */
    public function __construct($params = [])
    {
        //定义基本常量
        foreach (['name', 'env', 'debug'] as $v) {
            if (isset($params[$v])) {
                $this->$v = $params[$v];
            }
        }
        define('APP_ENV', $this->env, true);
        define('APP_NAME', $this->name, true);
        define('APP_DEBUG', $this->debug, true);
        if (!defined('APP_PATH')) {
            $reflection = new ReflectionClass(ClassLoader::class);
            $appPath = explode('/', str_replace('\\', '/', dirname(dirname($reflection->getFileName()))));
            array_pop($appPath);
            $appPath = implode("/", $appPath) . '/';
            define('APP_PATH', $appPath, true);
        }

        //默认错误设置
        error_reporting(APP_DEBUG ? E_ALL : 0);
        ini_set('display_errors', APP_DEBUG ? 'On' : 'Off');

        //
        if (defined('IN_SWOOLE')) {
            $timezone = getenv('timezone');
            date_default_timezone_set($timezone ? $timezone : 'Asia/Shanghai');
            Common::initial($timezone);
        } else {
            $server = $env = [];
            foreach ($_SERVER as $k => $v) {
                $server[strtolower($k)] = $v;
            }

            //
            if (php_sapi_name() !== 'cli') {
                $this->headers = $this->getRequestHeader();
                $this->params = $this->getRequestParams();
            } else
                $this->parseCmd();
        }
    }

    /**
     * @param Request|null $request
     * @param Response|null $response
     */
    public function __invoke(Request $request = null, Response $response = null)
    {
        $app = new App($this->headers, $this->params, $this->cookie, $this->server, $request, $response);
        $output = $app->__invoke();
        if (defined('IN_SWOOLE')) {
            if ($headers = $app->response->getHeader()) {
                foreach ($headers as $k => $v)
                    $response->header($k, $v);
            }
            $response->end($output);
        }

    }

    /**
     * @return array
     */
    final private function getRequestHeader()
    {
        $header = array();
        foreach ($_SERVER as $Key => $Value) {
            if (substr($Key, 0, 5) === 'HTTP_') {
                $header[strtolower(str_replace("HTTP_", "", $Key))] = $Value;
            }
        }

        //
        if (empty($header['service'])) {
            $pathInfo = $this->getPathInfo();
            if ($pathInfo) {
                $parts = explode("/", $pathInfo, 2);
                $header['service'] = array_shift($parts);
                if ($parts) {
                    $header['action'] = array_shift($parts);
                }
            }
        }
        $header['service'] = !empty($header['service']) ? $header['service'] : '';
        $header['action'] = !empty($header['action']) ? $header['action'] : '';

        if (isset($_SERVER['php_auth_user']))
            $header['username'] = (string)$_SERVER['php_auth_user'];
        if (isset($_SERVER['php_auth_pw']))
            $header['password'] = (string)$_SERVER['php_auth_pw'];
        if (isset($_SERVER['content_type']))
            $header['content_type'] = trim(strstr($_SERVER['content_type'], ';', true));
        return $header;
    }

    /**
     * @return array
     */
    final private function getRequestParams()
    {
        if (php_sapi_name() !== 'cli') {
            $contentType = isset($_SERVER['content_type']) ? $_SERVER['content_type'] : '';
            if (stristr($contentType, 'application/json') !== false) {
                $input = file_get_contents('php://input');
                $params = (array)json_decode($input, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $params = array(
                        'data' => $input,
                        'msg' => 'InvalidJSON',
                    );
                }
            } else {
                $params = array_merge($_GET, $_POST, $_FILES);
            }
            if (empty($this->headers['service'])) {
                $this->headers['service'] = !empty($params['service']) ? $params['service'] : 'Index';
                $this->headers['action'] = !empty($params['action']) ? $params['action'] : 'Index';
            }
            return $params;
        } else {
            $params = [];
            $str = !empty($GLOBALS['argv'][1]) ? $GLOBALS['argv'][1] : '';
            $ret = parse_url($str);
            $query = isset($ret['query']) ? $ret['query'] : '';
            if ($query) parse_str($query, $params);
            return $params;
        }
    }


    /**
     * @return string
     */
    final private function getPathInfo()
    {
        $pathInfo = '';
        if (php_sapi_name() !== 'cli') {
            if (!empty($_SERVER['path_info'])) {
                $pathInfo = $_SERVER['path_info'];
            } else {
                $uri = isset($_SERVER['request_uri']) ? $_SERVER['request_uri'] : '';
                $scriptName = isset($_SERVER['script_name']) ? $_SERVER['script_name'] : '';
                $baseName = "/" . basename($scriptName);
                $scriptName = str_replace($baseName, '', $scriptName);
                $uri = str_replace($baseName, '', $uri);
                $pathInfo = (string)substr($uri, strlen($scriptName));
            }
            $pathInfo = trim($pathInfo, "\t\r\n\0\x0B/");
        } else {
            $opts = getopt('e::', ['run:']);
            if (empty($opts['run'])) {
                $ret = parse_url($opts['run']);
                $pathInfo = isset($ret['path']) ? $ret['path'] : '';
            }
        }
        return $pathInfo;
    }

    /**
     *
     */
    final private function parseCmd()
    {
        $opts = getopt('e::', ['run:']);
        if (empty($opts['run'])) {
            fwrite(STDOUT, "need --run.\n");
            exit;
        }

        $matches = $params = $header = [];
        $ret = parse_url($opts['run']);
        if (!empty($ret['path'])) {
            $path = trim($ret['path'], "\t\n\r\0\x0B/ ");
            if (!preg_match("/^\/?(?<service>\w+)\/(?<action>(\w+\/?)+)$/", $path, $matches)) {
                fwrite(STDOUT, "invalid service or action.\n");
                exit;
            }
            $header['service'] = $matches['service'] ? $matches['service'] : 'Index';
            $header['action'] = $matches['action'] ? $matches['action'] : 'Index';
        } else {
            fwrite(STDOUT, "invalid path.\n");
            exit;
        }

        if (!empty($ret['query'])) {
            parse_str($ret['query'], $params);
        }
        $this->headers = $header;
        $this->params = $params;
    }
}