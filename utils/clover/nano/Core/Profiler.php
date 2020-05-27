<?php

namespace Clover\Nano\Core;

use Exception;

/**
 * Class Profiler
 * @package Clover\Nano\Core
 */
final class Profiler
{
    /**
     * @var App
     */
    private $app;

    /**
     * @var App
     */
    private $isEnable = false;

    /**
     *
     * @var int
     */
    private $initTime = 0;

    /**
     * @var int
     */
    private $maxLog = 0;

    /**
     *
     * @var array
     */
    private $timeUsage = [];

    /**
     *
     * @var array
     */
    private $query = [];

    /**
     *
     * @var array
     */
    private $debug = [];

    /**
     * @var array
     */
    private $config;

    /**
     * Profiler constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->config = $app->config->get('profiler');
        $this->maxLog = $this->config['maxLog'] ? $this->config['maxLog'] : 100;
        $this->isEnable = $this->config['enable'] ? $this->config['enable'] : false;
        $this->initTime = round(microtime(true), 1);
    }

    /**
     *
     */
    public function debug()
    {
        if ($this->isEnable)
            foreach (func_get_args() as $msg)
                $this->debug[] = $msg;
    }

    /**
     * @param null $enable
     * @return bool
     */
    public function enable($enable = null)
    {
        if ($enable !== null)
            $this->isEnable = $enable;
        return $this->isEnable;
    }

    /**
     * @return array
     */
    public function fetch()
    {
        $profiler = [
            'services' => $this->app->request->getHeader('service'),
            'action' => $this->app->request->getHeader('action'),
            'memusage' => $this->memUsage(),
            'cpuusage' => file_exists('/proc/loadavg') ? substr(file_get_contents('/proc/loadavg'), 0, 4) : false,
            'timeusage' => [
                'total' => $this->elapsed(true) . "ms",
            ],
            'debug' => $this->debug,
            'query' => $this->query,
            'params' => $this->app->request->getParam(null),
            'headers' => $this->app->request->getHeader(null),
        ];

        foreach ($this->timeUsage as $key => $time) {
            $profiler['timeusage'][$key] = round($time, 2) . 'ms';
        }
        return $profiler;
    }

    /**
     * @return string
     */
    public function memUsage()
    {
        return Common::fileSize2Unit(memory_get_usage());
    }

    /**
     * @param bool $milliSec
     * @return float
     */
    public function elapsed($milliSec = true)
    {
        if ($milliSec) {
            return round(((microtime(true)) - $this->initTime) * 1000, 2);
        } else {
            return round(microtime(true) - $this->initTime, 2);
        }
    }


    /**
     * @param $queryStr
     * @param $sTime
     * @param $category
     */
    public function saveQuery($queryStr, $sTime, $category)
    {
        try {
            if (!$this->isEnable || empty($this->config['queryLog' . ucfirst($category)]))
                return;

            $nowMilliSec = $this->elapsed();
            $milliSecond = round(($nowMilliSec + $sTime), 2);
            if (!isset($this->timeUsage[$category]))
                $this->timeUsage[$category] = 0;
            $this->timeUsage[$category] += $milliSecond;
            $elapsed = round($nowMilliSec, 2);
            $start = round($nowMilliSec - $milliSecond, 2);
            $this->query[] = str_pad("({$milliSecond}ms On {$start}-{$elapsed}ms)", 30, " ", STR_PAD_RIGHT) . str_pad($category,
                    5, " ", STR_PAD_LEFT) . ": $queryStr";
            if ($this->maxLog > 0 && count($this->query) > $this->maxLog)
                $this->query = array_slice($this->query, -1 * $this->maxLog);
            return;
        } catch (Exception $e) {
            return;
        }
    }
}