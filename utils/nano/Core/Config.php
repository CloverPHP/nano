<?php

namespace Clover\Nano\Core;

/**
 * Class Config
 * @package Clover\Nano\Core
 */
final class Config
{
    /**
     * @var App
     */
    private $app;

    /**
     * @var array
     */
    private $data = [];

    /**
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;

        //加载配置文件
        $common = sprintf("%s/configs/%s.php", APP_PATH, strtolower(APP_ENV));
        if (is_file($common)) {
            $this->data = (array)(include $common);
        } else {
            $this->data = [];
        }
    }


    /**
     * @param string|null $key
     * @param mixed $default
     * @return array|mixed|null
     */
    public function get($key = null, $default = null)
    {
        if ($key === null) {
            return $this->data;
        } elseif (!strpos($key, '.')) {
            return isset($this->data[$key]) ? $this->data[$key] : $default;
        } else {
            $parts = explode('.', $key);
            $data = &$this->data;
            while ($key = array_shift($parts)) {
                if (isset($data[$key])) {
                    $data = &$data[$key];
                } else {
                    return $default;
                }
            }
            return $data;
        }
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function set($key, $value)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->data[$k] = $v;
            }
            return $this;
        }
        if (!strpos($key, '.')) {
            $this->data[$key] = $value;
        } else {
            $parts = explode('.', $key);
            $data = &$this->data;
            while ($key = array_shift($parts)) {
                if (!isset($data[$key])) {
                    $data[$key] = [];
                }
                if (is_array($data[$key])) {
                    $data = &$data[$key];
                    if (count($parts) <= 1) {
                        $key = array_shift($parts);
                        $data[$key] = $value;
                        break;
                    }
                } else {
                    break;
                }
            }
        }
        return $this;
    }

    /**
     * @param $key
     * @return $this
     */
    public function delete($key)
    {
        if (is_array($key)) {
            foreach ($key as $k)
                $this->delete($k);
            return $this;
        }
        if (!strpos($key, '.')) {
            unset($this->data[$key]);
        } else {
            $parts = explode('.', $key);
            $data = &$this->data;
            while ($key = array_shift($parts)) {
                if (isset($data[$key])) {
                    $data = &$data[$key];
                }
                if (count($parts) <= 1) {
                    $key = array_shift($parts);
                    unset($data[$key]);
                    break;
                }
            }
        }
        return $this;
    }
}