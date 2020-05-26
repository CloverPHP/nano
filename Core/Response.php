<?php

namespace Clover\Nano\Core;

/**
 * 默认返回处理类
 */
final class Response
{
    /**
     *
     * @var App
     */
    private $app;

    /**
     *
     * @var string
     */
    private $status = 'ok';

    /**
     * @var string
     */
    private $error = '';

    /**
     * @var array
     */
    private $headers = [];

    /**
     *
     * @var array
     */
    private $data = [];

    /**
     * @var \Swoole\Http\Response
     */
    private $swoole;


    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * @param \Swoole\Http\Response $response
     */
    public function setSwoole(\Swoole\Http\Response $response)
    {
        $this->swoole = $response;
    }


    /**
     * @param $name
     * @param $data
     * @return $this
     */
    public function add($name, $data)
    {
        if (!empty($name)) {
            if (!isset($this->data[$name]) || !is_array($this->data[$name]) || !is_array($data)) {
                $this->data[$name] = $data;
            } else {
                $this->data[$name] = array_merge($this->data[$name], $data);
            }
        }
        return $this;
    }

    /**
     * @param $name
     * @param $data
     * @return $this
     */
    public function change($name, $data)
    {
        if (!empty($name))
            $this->data[$name] = $data;
        return $this;

    }

    /**
     * @param $name
     * @return $this
     */
    public function delete($name)
    {
        if (!empty($name))
            unset($this->data[$name]);
        return $this;
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function get($name)
    {
        if (!empty($name)) {
            return !empty($this->data[$name]) ? $this->data[$name] : null;
        } else {
            return null;
        }
    }

    /**
     *
     */
    public function clear()
    {
        $this->data = [];
    }

    /**
     * @param $data
     */
    public function merge($data)
    {
        $this->data = array_replace_recursive($this->data, $data);
    }


    /**
     * @param $name
     * @param $value
     * @param $expire
     * @param $path
     * @param $domain
     * @param $secure
     * @param $httpOnly
     * @param $sameSite
     * @return bool
     */
    public function setCookie($name, $value = null, $expire = null, $path = null, $domain = null, $secure = null, $httpOnly = null, $sameSite = null)
    {
        if (defined('IN_SWOOLE')) {
            return $this->swoole->setCookie($name, $value, $expire, $path, $domain, $secure, $httpOnly, $sameSite);
        } elseif (!headers_sent()) {
            return setcookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
        } else
            return false;
    }

    /**
     * @param $data
     */
    private function removeDebug(&$data)
    {
        if (!APP_DEBUG)
            unset($data['profiler'], $data['systrace'], $data['desc']);
    }

    /**
     * @param $name
     * @param null $value
     * @param bool $overwrite
     * @return mixed|null
     */
    final public function setHeader($name, $value = null, $overwrite = false)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v)
                $this->setHeader($k, $v, $overwrite);
        } elseif (!empty($value) && !empty($name) && is_string($name)) {
            if (!$overwrite && isset($this->headers[$name]))
                return $this->headers[$name];
            if (is_array($value) || is_object($value))
                $value = json_encode($value);
            $this->headers[$name] = (string)$value;
        }
        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

    /**
     * @param array|string $name
     * @param mixed $default
     * @return array|mixed|null
     */
    final public function getHeader($name = null, $default = null)
    {
        if ($name === null)
            return $this->headers;
        elseif (isset($this->header[$name]))
            return $this->headers[$name];
        else
            return $default;
    }


    /**
     * Magic Function _get to get the response item
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        } else {
            return null;
        }
    }

    /**
     * Magic Function __isset to check the response item exist
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        if (isset($this->$name)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Output the buffer
     * @return mixed
     */
    public function fetch()
    {
        $data = array_merge($this->data, array(
            'status' => $this->status,
            'error' => $this->error,
            'code' => "",
            'profiler' => $this->app->profiler->fetch()
        ));
        $this->removeDebug($data);
        return $data;
    }

    /**
     * @param bool $ignoreAbort
     */
    public function disconnect($ignoreAbort = false)
    {
        $data = $this->fetch();
        $string = json_encode($data);
        $length = strlen($string);
        if (defined('IN_SWOOLE')) {
            $this->swoole->end($string);
        } else {
            $this->setHeader('Connection', 'close');
            $this->setHeader('Content-Length', $length);
            echo $string;
            flush();
            if ($ignoreAbort)
                ignore_user_abort(true);
        }
    }

    /**
     * @param $content
     * @return bool
     */
    final public function output($content = null)
    {
        if (headers_sent())
            return false;
        if (!$content)
            $content = $this->fetch();

        switch (gettype($content)) {
            case 'integer':
            case 'double':
            case 'float':
            case 'string':
                $content = (string)$content;
                break;
            case 'array':
            case 'object':
                $content = json_encode($content);
                break;
            case 'resource':
                break;
            default:
                $content = '';
        }

        //
        $acceptEncode = !empty($this->app->request->getHeader("accept_encoding")) ? $this->app->request->getHeader("accept_encoding") : '';
        if (strlen($content) > 1024 && strpos($acceptEncode, 'deflate') !== (boolean)false) {
            $content = gzdeflate($content, 9);
            $this->setHeader("Content-Encoding", "deflate", true);
        }

        //
        $length = strlen($content);
        $this->setHeader([
            "Cache-Control" => "max-age=0, must-revalidate",
            "Expires" => gmdate("D, d M Y H:i:s", (int)Common::timestamp()) . " GMT",
            "Content-Length" => $length,
        ], true);
        $eTag = sprintf('%s-%s-%s', mb_strlen($content), sha1($content), hash('crc32b', $content));
        if ($length > 0)
            $this->app->response->setHeader("ETag", $eTag, true);

        //Additional Header add-on;
        foreach ($this->headers as $headerName => $headerContent)
            header("{$headerName}: {$headerContent}");

        //
        $requestETag = !empty($this->app->request->getHeader("if_none_match")) ? trim($this->app->request->getHeader("if_none_match")) : false;
        if ($requestETag === $eTag) {
            $responseCode = http_response_code();
            if ($responseCode === 200 || $responseCode === false)
                http_response_code(304);
        }

        //
        echo $content;

        ignore_user_abort(true);
        flush();
        clearstatcache();
        return true;
    }
}