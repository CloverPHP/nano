<?php

declare(strict_types=1);

namespace Clover\Nano;

final class EnvConfig
{
    private static $instance;

    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @var array
     */
    private static $config = [];

    /**
     * @param string $keys
     * @param mixed $default
     * @return null|mixed
     */
    public function get($keys, $default = null)
    {
        $keys = explode('.', strtolower($keys));
        if (empty($keys))
            return $default;

        //
        $file = array_shift($keys);
        if (empty(self::$config[$file])) {
            $file = APP_PATH . 'configs/envconf/' . $file . '.php';
            if (!is_file($file))
                return $default;
            self::$config[$file] = include $file;
        }

        //
        $config = &self::$config[$file];
        while ($keys) {
            $key = array_shift($keys);
            if (!isset($config[$key])) {
                $config = $default;
                break;
            }
            $config = $config[$key];
        }
        return $config;
    }
}