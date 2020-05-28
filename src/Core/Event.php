<?php

namespace Clover\Nano\Core;

/**
 * Class Event
 * @package Clover\Nano\Core
 */
final class Event
{
    private $listeners = [];
    private $timerListener = [];
    private $scheduleListener = [];
    private $termListener = [];
    private $eventParams = [];
    private $timeInterval = 0;
    private $timerOut = 0;
    private $tick = 0;
    private $timerLastTimestamp = 0;

    /**
     * EventEmitter constructor.
     * @param array $eventParams the params will be pass when a emit.
     */
    public function __construct(array $eventParams = [])
    {
        $this->setParams($eventParams);
    }

    /**
     * Setup Emitter Parameter
     * @param array $eventParams the params will be pass when a emit.
     */
    public function setParams(array $eventParams)
    {
        $this->eventParams = $eventParams;
    }

    /**
     * Execute/Fire an terminal Event
     */
    public function launchSignalListener()
    {
        if(defined('IN_SWOOLE'))
            return;

        //Register Signal for event
        declare(ticks=1);
        pcntl_signal(SIGKILL, [$this, 'emitTermSignal']);
        pcntl_signal(SIGINT, [$this, 'emitTermSignal']);
        pcntl_signal(SIGQUIT, [$this, 'emitTermSignal']);
        pcntl_signal(SIGSTOP, [$this, 'emitTermSignal']);
        pcntl_signal(SIGTERM, [$this, 'emitTermSignal']);
        pcntl_signal(SIGHUP, [$this, 'emitTermSignal']);
        pcntl_signal(SIGUSR1, [$this, 'emitTermSignal']);
        pcntl_signal(SIGUSR2, [$this, 'emitTermSignal']);
        pcntl_signal(SIGPIPE, [$this, 'emitTermSignal']);
        pcntl_signal(SIGPOLL, [$this, 'emitTermSignal']);
        pcntl_signal(SIGXCPU, [$this, 'emitTermSignal']);
    }

    /**
     * @param callable $listener Event Listener to register
     * @param int $priority Event Priority
     * @return bool
     */
    public function onTermSignal(callable $listener, $priority = 0)
    {
        $this->termListener[] = ["listener" => $listener, "priority" => $priority];
        Common::multiArraySort($this->termListener, ["priority" => [SORT_DESC, SORT_NATURAL]]);
        return true;
    }

    /**
     * Register Once Time Listener
     * @param string $event Event Name to register
     * @param callable $listener Event Listener to register
     * @param int $priority Event Priority
     * @return bool
     */
    public function once($event, callable $listener, $priority = 0)
    {
        return self::on($event, $listener, 1, $priority);
    }

    /**
     * @param string $event Event Name to register
     * @param callable $listener Event Listener to register
     * @param int $times Event available fire times, 0 = not limit.
     * @param int $priority Event Priority
     * @return bool
     */
    public function on($event, callable $listener, $times = 0, $priority = 0)
    {
        if (is_string($event) && !empty($event)) {
            if (!isset($this->listeners[$event]))
                $this->listeners[$event] = [];
            $this->listeners[$event][] = ["listener" => $listener, "times" => $times, "priority" => $priority];
            Common::multiArraySort($this->listeners[$event], ["priority" => [SORT_DESC, SORT_NATURAL]]);
            return true;
        } else
            return false;
    }

    /**
     * Turn off one off event listener if listener is same.
     * @param string $event Event Name to turn off
     * @param callable $listener Event Listener to turn off
     * @param bool $all Remove only match or all match event listener
     * @return bool
     */
    public function off($event, callable $listener, $all = true)
    {
        if (is_string($event) && !empty($event) && isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $key => $event)
                if ($event['listener'] === $listener) {
                    unset($this->listeners[$event][$key]);
                    if (!$all)
                        break;
                }
        }
        return true;
    }

    /**
     * Remove Event
     * @param string $event Event Name to remove
     * @return bool
     */
    public function removeEvent($event)
    {
        if (is_string($event) && !empty($event) && isset($this->listeners[$event])) {
            unset($this->listeners[$event]);
            return true;
        } else
            return false;
    }

    /**
     * Event on every n tick
     * @param callable $listener
     * @param int $tick
     * @param int $times
     * @param int $priority
     * @return bool
     */
    public function onTick(callable $listener, $tick = 1, $times = 0, $priority = 0)
    {
        $event = 'timerTick';
        if (!isset($this->timerListener[$event]) && !empty($interval)) {
            $this->timerListener[$event] = [];
        }

        $this->timerListener[$event][] = ["listener" => $listener, "tick" => $tick, "times" => $times, "priority" => $priority];
        Common::multiArraySort($this->timerListener[$event], ["priority" => [SORT_DESC, SORT_NATURAL]]);
        return true;
    }

    /**
     * 支持 (*), 但不支持 (* / N)
     * Event on Schedule (like cron)
     * @param string $schedule
     * @param callable $listener
     * @param int $times
     * @param int $priority
     * @return bool
     */
    public function onSchedule($schedule, callable $listener, $times = 0, $priority = 0)
    {
        if (self::scheduleValidate($schedule)) {
            if (!isset($this->scheduleListener[$schedule]))
                $this->scheduleListener[$schedule] = [];
            $this->scheduleListener[$schedule][] = ["listener" => $listener, "times" => $times, "priority" => $priority];
            Common::multiArraySort($this->scheduleListener[$schedule], ["priority" => [SORT_DESC, SORT_NATURAL]]);
            return true;
        } else
            return false;
    }

    /**
     * Validate schedule format is valid or not.
     * @param string $schedule
     * @return boolean
     */
    private function scheduleValidate($schedule)
    {
        $parts = explode(" ", $schedule);
        if (6 !== count($parts))
            return false;
        foreach ($parts as $part)
            if (preg_match("/[^,\\-*0-9]|(,,)|(--)/", $part))
                return false;
        return true;
    }

    /**
     * Launch
     * @param int $timeout Timer Stop Timing
     * @param int $interval Timer Interval
     * @return bool
     * @internal param $event
     */
    public function launchTimer($timeout, $interval = 1000)
    {
        $this->timerOut = (int)$timeout;
        $this->timeInterval = (int)$interval;
        $this->timerLastTimestamp = ceil(Common::timestamp(true));

        $this->emit('timerStart');

        while ($this->timerTick())
            usleep($this->timeInterval * 1000);

        $this->emit('timerStop');
        return true;
    }

    /**
     * Execute/Fire an event
     * @param $eventName
     * @param array $args
     * @return bool
     */
    public function emit($eventName, array $args = [])
    {
        if (is_string($eventName) && !empty($eventName) && isset($this->listeners[$eventName])) {
            foreach ($this->listeners[$eventName] as $key => &$event)
                if (isset($event['listener'])) {
                    $result = $this->eventFire($event['listener'], $args);
                    $event['times']--;
                    if ($event['times'] === 0)
                        unset($this->listeners[$event][$key]);
                    if ($result === false)
                        break;
                }
            return true;
        } else
            return false;
    }

    /**
     * @param $callable
     * @param mixed $args
     * @return mixed
     */
    private function eventFire(callable $callable, $args = [])
    {
        if (!is_array($args))
            $args = [$args];
        return call_user_func_array($callable, array_merge($this->eventParams, $args));
    }

    /**
     * Timer Tick
     * @return bool
     */
    private function timerTick()
    {
        $elapsed = $this->elapsed();
        if ($elapsed <= $this->timerOut) {
            $now = ceil(Common::timestamp(true));
            $this->emitSchedule();
            $offset = $now - $this->timerLastTimestamp - 1;
            $this->timerEmit('timerTick', $now, $offset);
            $this->timerLastTimestamp = $now;
            $this->tick++;
            return true;
        } else {
            return false;
        }
    }

    /**
     * get elapsed milli seconds
     * @param bool $milliSecond
     * @param bool $current Start Current or initial time
     * @return int
     */
    public function elapsed($milliSecond = false, $current = false)
    {
        $now = Common::timestamp($current);
        return (true === $milliSecond) ? floor((microtime(true) - $now) * 1000) : floor(microtime(true) - $now);
    }

    /**
     * Execute/Fire an schedule
     * @return bool
     */
    public function emitSchedule()
    {
        if (!empty($this->scheduleListener)) {
            foreach ($this->scheduleListener as $schedule => &$events)
                if ($this->scheduleMatch($schedule)) {
                    foreach ($events as $key => &$event)
                        if (isset($event['listener'])) {
                            $result = $this->eventFire($event['listener'], $schedule);
                            $event['times']--;
                            if (0 === $event['times'])
                                unset($events[$key]);
                            if ($result === false)
                                break;
                        }
                }
            return true;
        } else
            return false;
    }

    /**
     * @param $schedule
     * @return bool
     */
    private function scheduleMatch($schedule)
    {
        $date = explode(":", date("s:i:H:d:m:N"));
        $schedule = explode(" ", $schedule);
        for ($i = 0; $i < 6; $i++) {
            if ("*" === $schedule[$i] || $date[$i] === $schedule[$i]) {
                continue;
            } elseif (false !== strpos($schedule[$i], ',')) {
                if (in_array(ltrim($date[$i], '0'), explode(",", $schedule[$i]), true)) {
                    continue;
                }
            } elseif (false !== strpos($schedule[$i], '-')) {
                $parts = explode("-", $schedule[$i]);
                if (in_array((int)ltrim($date[$i], '0'), range($parts[0], $parts[1]), true)) {
                    continue;
                }
            }
            return false;
        }
        return true;
    }

    /**
     * Execute/Fire an event
     * @param $eventName
     * @param int $timestamp
     * @param int $offset
     * @return bool
     * @internal param string $event Event Name
     */
    private function timerEmit($eventName, $timestamp, $offset)
    {
        if (is_string($eventName) && !empty($eventName) && isset($this->timerListener[$eventName])) {
            foreach ($this->timerListener[$eventName] as $key => $event)
                if (isset($event['listener']) && is_callable($event['listener'])) {
                    if (!isset($event['tick']) || (int)$event['tick'] < 1)
                        $emit = true;
                    elseif ($this->tick % (int)$event['tick'] === 0)
                        $emit = true;
                    else
                        $emit = false;
                    if ($emit) {
                        $result = $this->eventFire($event['listener'], [$event, $timestamp, $offset]);
                        $event['times']--;
                        if (0 === $event['times'])
                            unset($this->timerListener[$event][$key]);
                        if ($result === false)
                            break;
                    }
                }
            return true;
        } else
            return false;
    }

    /**
     * Execute/Fire an terminal Event
     * @param int $sigNo
     */
    private function emitTermSignal($sigNo = 0)
    {
        $this->eventParams[] = $sigNo;
        foreach ($this->termListener as $key => &$event)
            if (isset($event['listener'])) {
                $result = $this->eventFire($event['listener'], $sigNo);
                if ($result === false)
                    break;
            }
    }
}