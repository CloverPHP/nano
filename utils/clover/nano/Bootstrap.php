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
    /**
     * Boot constructor.
     */
    public function __construct()
    {
    }

    /**
     * 处理一个请求
     * @param Request|null $request
     * @param Response|null $response
     */
    public function __invoke(Request $request = null, Response $response = null)
    {
        //
        $header = $params = $cookie = $server = [];
        if (defined('IN_SWOOLE'))
            $this->parseSwoole($header, $params, $cookie, $server, $request, $response);
        elseif (php_sapi_name() !== 'cli')
            $this->parseCgi($header, $params, $cookie, $server, $request, $response);
        else
            $this->parseCmd($header, $params, $cookie, $server, $request, $response);

        $app = new App($header, $params, $cookie, $server, $request, $response);
        $output = $app->__invoke();
        if (defined('IN_SWOOLE')) {
            if ($header = $app->response->getHeader()) {
                foreach ($header as $k => $v)
                    $response->header($k, $v);
            }
            $response->end(json_encode($output));
            unset($app);
        }else
            unset($app);
    }


    /**
     * 解析cgi/fpm的参数
     * @param array $header
     * @param array $params
     * @param array $cookie
     * @param array $server
     * @param Request|null $request
     * @param Response|null $response
     */
    final private function parseCgi(&$header, &$params, &$cookie, &$server, $request = null, $response = null)
    {
        foreach ($_COOKIE as $key => $value)
            $cookie[$key] = $value;
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'http_')
                $header[strtolower(str_replace("http_", "", $key))] = $value;
            else
                $server[$key] = $value;
        }
        if (!isset($this->server['path_info']))
            $server['path_info'] = isset($server['request_uri']) ? ['request_uri'] : '/';
        $mime = isset($this->server['content_type']) ? $server['content_type'] : '';
        $params = stristr($mime, 'json') === false ? array_replace($_GET, $_POST, $_FILES)
            : (array)json_decode(file_get_contents('php://input'), true);
    }

    /**
     * 解析传统命令行的参数
     * @param array $header
     * @param array $params
     * @param array $cookie
     * @param array $server
     * @param Request|null $request
     * @param Response|null $response
     */
    final private function parseCmd(&$header, &$params, &$cookie, &$server, $request = null, $response = null)
    {
        //
        $header = $cookie = [];
        foreach ($_SERVER as $key => $value)
            $server[$key] = $value;

        //
        $opts = getopt('r::', ['run:']);
        $run = parse_url($opts['run']);
        $server['path_info'] = !empty($run['path']) ? $run['path'] : '/';
        !empty($run['query']) ? parse_str($run['query'], $params) : [];
        if (empty($run['path'])) {
            fwrite(STDOUT, "example:/usr/bin/php path/to/index.php --run=/path/to/service?query_params" . PHP_EOL);
            exit(0);
        }
    }

    /**
     * 解析swoole请求的参数
     * @param array $header
     * @param array $params
     * @param array $cookie
     * @param array $server
     * @param Request $request
     * @param Response $response
     */
    final private function parseSwoole(&$header, &$params, &$cookie, &$server, Request $request, Response $response)
    {
        $header = (array)$request->header;
        $cookie = (array)$request->cookie;
        $servers = (array)$request->server;
        foreach ($servers as $key => $value) {
            if (substr($key, 0, 5) === 'http_')
                $header[strtolower(str_replace("http_", "", $key))] = $value;
            else
                $server[$key] = $value;
        }

        if (!isset($server['path_info']))
            $server['path_info'] = '/';

        $mime = isset($this->server['content_type']) ? $server['content_type'] : '';
        $params = stristr($mime, 'json') === false
            ? array_replace((array)$request->get, (array)$request->post, (array)$request->files)
            : (array)json_decode($request->rawContent(), true);
    }
}