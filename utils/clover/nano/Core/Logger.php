<?php

namespace Clover\Nano\Core;

/**
 * Class Logger
 * @package Clover\Nano\Core
 */
final class Logger
{
    const EMERGENCY = 1;
    const ALERT = 2;
    const CRITICAL = 4;
    const ERROR = 8;
    const WARNNING = 16;
    const NOTICE = 32;
    const INFO = 64;
    const DEBUG = 128;
    const LOG = 256;

    private $levelName = [
        self::EMERGENCY => "EMERGENCY",
        self::ALERT => "ALERT",
        self::CRITICAL => "CRITICAL",
        self::ERROR => "ERROR",
        self::WARNNING => "WARNNING",
        self::NOTICE => "NOTICE",
        self::INFO => "INFO",
        self::DEBUG => "DEBUG",
        self::LOG => "LOG",
    ];

    /**
     * @var App
     */
    private $app;

    /*
     * 文件
     * @var string
     */
    private $logFile = '';

    /*
     * 等级
     * @var string
     */
    private $level = 0;

    /**
     * 日志模式
     * @var string
     */
    private $writeMethod = 'writeFile';

    /**
     * Logger constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $config = $this->app->config->get('logger');
        if (!empty($config['mode']) && $config['mode'] === 'syslog') {
            $this->writeMethod = 'writeSyslog';
        } else {
            $this->writeMethod = 'writeFile';
            $logPath = !empty($config['path']) ? $config['path'] : '/tmp/';
            $logFile = !empty($config['file']) ? $config['file'] : APP_NAME;
            if (empty($config['file']) || !file_exists($config['file'])) {
                $this->logFile = "{$logPath}{$logFile}-" . date("Ymd") . ".log";
            }else{
                $this->logFile = $config['file'];
            }
        }
        if (isset($config['level'])) {
            $this->level = (int)$config['level'];
        }
    }

    /**
     * @param $string
     * @param array $context
     */
    public function emergency($string, $context = array())
    {
        if ($this->level & self::EMERGENCY) {
            $this->write($string, $context, self::EMERGENCY);
        }
    }

    /**
     * @param $string
     * @param array $context
     */
    public function alert($string, array $context = array())
    {
        if ($this->level & self::ALERT) {
            $this->write($string, $context, self::ALERT);
        }
    }

    /**
     * @param $string
     * @param array $context
     */
    public function critical($string, array $context = array())
    {
        if ($this->level & self::CRITICAL) {
            $this->write($string, $context, self::CRITICAL);
        }
    }

    /**
     * @param $string
     * @param array $context
     */
    public function error($string, array $context = array())
    {
        if ($this->level & self::ERROR) {
            $this->write($string, $context, self::ERROR);
        }
    }

    /**
     * @param $string
     * @param array $context
     */
    public function warning($string, array $context = array())
    {
        if ($this->level & self::WARNNING) {
            $this->write($string, $context, self::WARNNING);
        }
    }

    /**
     * @param $string
     * @param array $context
     */
    public function notice($string, array $context = array())
    {
        if ($this->level & self::NOTICE) {
            $this->write($string, $context, self::NOTICE);
        }
    }

    /**
     * @param $string
     * @param array $context
     */
    public function info($string, array $context = array())
    {
        if ($this->level & self::INFO) {
            $this->write($string, $context, self::INFO);
        }
    }

    /**
     * @param $string
     * @param array $context
     */
    public function debug($string, array $context = array())
    {
        if ($this->level & self::DEBUG) {
            $this->write($string, $context, self::DEBUG);
        }
    }

    /**
     * @param $string
     * @param array $context
     */
    public function log($string, array $context = array())
    {
        if ($this->level & self::LOG) {
            $this->write($string, $context, self::LOG);
        }
    }

    /**
     * @param $string
     * @param $context
     * @param $level
     */
    public function write($string, $context, $level)
    {
        $method = $this->writeMethod;
        $log = sprintf("%s [%s] %s\n", date('Y-m-d H:i:s'), $this->levelName[$level], $string);
        $this->$method($log, $level);
    }

    /**
     * @param $log
     * @param $level
     */
    private function writeFile($log, $level)
    {
        if ($this->logFile) {
            $string = preg_replace("/\s+/", " ", $log);
            file_put_contents($this->logFile, $string . "\n", FILE_APPEND);
        }
    }

    /**
     * @param $log
     * @param $level
     */
    private function writeSyslog($log, $level)
    {
        syslog($level, $log);
    }
}