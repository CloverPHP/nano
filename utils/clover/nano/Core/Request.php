<?php

namespace Clover\Nano\Core;

use Clover\Nano\Exception\InvalidParams;

/**
 * @property $service
 * @property $action
 */
final class Request
{
    /**
     *
     * @var App
     */
    private $app;

    /**
     * @var string
     */
    private $uri = '';

    /**
     *
     * @var array
     */
    private $header = [];

    /**
     *
     * @var array
     */
    private $params = [];

    /**
     * @var array
     */
    private $cookie = [];

    /**
     * @var \Swoole\Http\Response
     */
    private $swoole;

    /**
     * @param App $app
     * @param $header
     * @param $params
     * @param $cookie
     */
    public function __construct($app, $header = [], $params = [], $cookie = [])
    {
        $this->app = $app;
        $this->header = $header;
        $this->params = $params;
        $this->cookie = $cookie;
    }

    /**
     * @param \Swoole\Http\Request $request
     */
    public function setSwoole(\Swoole\Http\Request $request)
    {
        $this->swoole = $request;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        if (in_array($name, ['service', 'action'], true)) {
            return $this->$name;
        } else {
            return null;
        }
    }

    /**
     * @param string $name
     * @param null $default
     * @return mixed
     */
    public function getHeader($name = null, $default = null)
    {
        if ($name === null)
            return $this->header;
        elseif (isset($this->header[$name]))
            return $this->header[$name];
        else
            return $default;
    }

    /**
     * Get API Parameter Item
     * @param string $name
     * @param null $default
     * @return mixed
     */
    public function getParam($name = null, $default = null)
    {
        if ($name === null)
            return $this->params;
        elseif (isset($this->params[$name]))
            return $this->params[$name];
        else
            return $default;
    }

    /**
     * @param string|null $name
     * @param string|null $default
     * @return array|mixed
     */
    public function getCookie($name = null, $default = null)
    {
        if ($name === null)
            return $this->cookie;
        elseif (isset($this->cookie[$name]))
            return $this->cookie[$name];
        else
            return $default;
    }

    /**
     * @return string
     */
    public function getRequestMethod()
    {
        $method = $this->app->getServerParam('request_method', '');
        return $method;
    }

    /**
     * @return bool
     */
    public function isGet()
    {
        $method = $this->app->getServerParam('request_method', '');
        return (strtoupper($method) === 'GET');
    }

    /**
     * @return bool
     */
    public function isPost()
    {
        $method = $this->app->getServerParam('request_method', '');
        return (strtoupper($method) === 'POST');
    }

    /**
     * @return bool
     */
    public function isXhr()
    {
        $method = $this->app->getServerParam('http_x_requested_with', '');
        return (strtoupper($method) === 'XMLHTTPREQUEST');
    }


    /**
     * @param array $header
     */
    final public function setHeader(array $header)
    {
        $this->header = array_replace_recursive($this->header, $header);
    }

    /**
     * @param array $params
     */
    final public function setParams(array $params)
    {
        $this->params = array_replace_recursive($this->params, $params);
    }

    /**
     * @return string
     */
    final public function getUri()
    {
        return $this->uri;
    }

    /**
     * @param $uri
     */
    final public function setUri($uri)
    {
        $this->uri = $uri;
    }

    /**
     * Validate all the parameter accordingly requirement
     * @param string|int|array $patterns
     * @param array $data
     * @throws InvalidParams
     */
    final public function validate($patterns, $data = [])
    {
        if (!$data) $data = $this->params;
        $needed = Common::validator($patterns, $data);
        reset($needed);
        $code = key($needed);
        if (is_array($needed) && !empty($needed))
            throw new InvalidParams("Invalid Parameter.", $code, ['needed' => $needed]);
    }
}