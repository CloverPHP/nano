<?php

namespace Clover\Nano\Core;

use Exception;
use Throwable;
use ReflectionClass;
use ReflectionException;
use Clover\Nano\Controller;
use Clover\Nano\Exception\Base;
use Clover\Nano\Exception\Normal;
use Clover\Nano\Exception\DBQueryError;
use Clover\Nano\Exception\InternalError;
use Clover\Nano\Exception\UnexpectedError;

/**
 * Class App
 * @package Clover\Nano\Core
 * @property Db $db
 * @property Redis $redis
 * @property Config $config
 * @property Event $event
 * @property Logger $logger
 * @property Request $request
 * @property Response $response
 * @property Profiler $profiler
 *
 */
class App
{
    /**
     * @var Event
     */
    private $event;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Profiler
     */
    private $profiler;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var bool
     */
    private $isIgnoreError = true;

    /**
     * @var array
     */
    private $components = [];

    /**
     * @var array
     */
    private $server = [];

    /**
     * @param array $header
     * @param array $params
     * @param array $cookie
     * @param array $server
     * @param \Swoole\Http\Request|null $request
     * @param \Swoole\Http\Response|null $response
     */
    public function __construct(array $header = [], array $params = [], array $cookie = [], array $server = []
        , \Swoole\Http\Request $request = null, \Swoole\Http\Response $response = null)
    {
        //新建核心类
        $this->server = $server;
        $this->components[Event::class] = $this->event = new Event();
        $this->components[Config::class] = $this->config = new Config($this);
        $this->components[Logger::class] = $this->logger = new Logger($this);
        $this->components[Profiler::class] = $this->profiler = new Profiler($this);
        $this->components[Request::class] = $this->request = new Request($this, $header, $params, $cookie);
        $this->components[Response::class] = $this->response = new Response($this);
        if (defined('IN_SWOOLE')) {
            $this->request->setSwoole($request);
            $this->response->setSwoole($response);
        }

        //错误处理和时区
        $this->handleError();
        $timezone = $this->config->get('timezone');
        if (!defined('IN_SWOOLE') && $timezone) {
            date_default_timezone_set($timezone);
            Common::initial($timezone);
        }

        //加载预定义钩子
        $hooks = $this->config->get('hook');
        if ($hooks) foreach ($hooks as $hook => $callback) {
            $this->event->on($hook, $callback);
        }
    }

    /**
     */
    public function __invoke()
    {
        $output = [];
        try {
            $ctrlName = $this->request->getHeader('controller', Controller::class);
            if (class_exists($ctrlName)) {
                $class = new ReflectionClass($ctrlName);
                $this->event->emit('access_check', [$this]);
                $ctrl = $class->newInstanceArgs([$this]);
                $ctrl->__invoke($this);
                $this->commit();
                $output = $this->response->fetch();
            } else
                throw new InternalError("Controller Not Found {$ctrlName}");
        } catch (Base $ex) {
            try {
                $this->ignoreError(true);
                if ($ex instanceof Normal) {
                    $this->commit();
                    $output = $this->response->fetch();
                } else {
                    if ($ex instanceof UnexpectedError)
                        $this->event->emit('unexpected_error', [$this, $ex]);
                    else
                        $this->event->emit('runtime_error', [$this, $ex]);
                    $output = $ex->fetch();
                    $output['profiler'] = $this->profiler->fetch();
                }
            } catch (Throwable $ex) {//php7.0+
                $output = $this->handleException($ex);
            } catch (Exception $ex) {//php5.6.x
                $output = $this->handleException($ex);
            }
        } catch (Throwable $ex) {//php7.0+
            echo $ex->getMessage();
            $output = $this->handleException($ex);
        } catch (Exception $ex) {//php5.6.x
            $output = $this->handleException($ex);
        }

        $this->event->emit('access_log', [$this, &$output]);
        $this->response->output($output);
        return $output;

    }

    /**
     * @throws DBQueryError
     */
    public function commit()
    {
        $this->event->emit('before_commit', [$this]);
        $this->dbCommit();
        $this->event->emit('after_commit', [$this]);
    }

    /**
     * @throws DBQueryError
     */
    public function dbCommit()
    {
        $this->event->emit('db_commit', [$this]);
        if (isset($this->components[Db::class]) && $this->components[Db::class] instanceof Db) {
            $this->db = $this->components[Db::class];
            if ($this->db->commit()) {
                $this->event->emit('commit_done', [$this]);
                return true;
            } else {
                $this->event->emit('commit_fail', [$this]);
                return false;
            }
        }
        return true;
    }

    /**
     * @throws DBQueryError
     */
    public function rollback()
    {
        $this->event->emit('before_rollback', [$this]);
        $this->dbRollback();
        $this->event->emit('after_rollback', [$this]);
    }

    /**
     * @throws DBQueryError
     */
    public function dbRollback()
    {
        $this->event->emit('db_rollback', [$this]);
        if (isset($this->components[Db::class]) && $this->components[Db::class] instanceof Db) {
            $this->db = $this->components[Db::class];
            if ($this->db->rollback()) {
                $this->event->emit('rollback_done', [$this]);
                return true;
            } else {
                $this->event->emit('rollback_fail', [$this]);
                return false;
            }
        }
        return true;
    }

    /**
     * @param $key
     * @param $default
     * @return array
     */
    public function getEnv($key, $default = null)
    {
        return isset($_ENV[$key]) ? $_ENV[$key] : $default;
    }

    /**
     * @param $key
     * @param $default
     * @return mixed
     */
    public function getServerParam($key, $default = null)
    {
        return isset($this->server[$key]) ? $this->server[$key] : $default;
    }


    /**
     * @param bool $ignore
     * @return bool
     */
    public function ignoreError($ignore = null)
    {
        if ($ignore !== null)
            $this->isIgnoreError = $ignore;
        return $this->isIgnoreError;
    }

    /**
     *
     */
    public function handleError()
    {
        if (defined('IN_SWOOLE'))
            return;

        //1.捕捉致命错误
        register_shutdown_function(function () {
            if ($this->ignoreError())
                return;
            $error = error_get_last();
            if (!empty($error) && is_array($error)) {
                $errno = isset($error["type"]) ? (string)$error["type"] : '';
                $errfile = isset($error["file"]) ? (string)$error["file"] : '';
                $errline = isset($error["line"]) ? (string)$error["line"] : '';
                $errstr = isset($error["message"]) ? (string)$error["message"] : '';
                $string = "{$errno}:{$errstr}@{$errfile}:{$errline}";
                $output = array_merge(array(
                    'status' => 'error',
                    'error' => 'unexpected_error',
                    'code' => 'shutdown',
                    'desc' => $string,
                ));
                $this->logger->emergency($string);
                $this->response->output($output);
                //exit(0);
            }
        });

        //2.设置自定义错误处理
        set_error_handler(function ($errno, $error, $errFile, $errLine, $clear = false) {
            if ($this->ignoreError())
                return;
            $stack = debug_backtrace(0);
            $systrace = "({$errno}) {$error} in {$errFile} on line {$errLine}, from -> ";
            for ($i = count($stack) - 1; $i >= 0; $i--) {
                if (isset($stack[$i]['file']))
                    $systrace .= "{$stack[$i]['file']} at line {$stack[$i]['line']}" . (!empty($i) ?
                            " \n-> " : "");
            }
            $output = array_merge(array(
                'status' => 'error',
                'error' => 'unexpected_error',
                'code' => 'error',
                'desc' => "{$errno}:{$error}@{$errFile}:{$errLine}",
                'profiler' => $this->profiler->fetch()
            ));
            $output['systrace'] = $systrace;
            $this->logger->emergency($output['desc'], APP_NAME . '-Error');

            //根据环境变量处理错误
            if (APP_DEBUG && !in_array($errno, [E_NOTICE, E_DEPRECATED], true)) {
                $this->response->output($output);
                exit(0);
            } else {
                $this->profiler->debug($systrace);
            }
        });

        //3.设置自定义异常处理
        set_exception_handler([$this, 'handleException']);
    }

    /**
     * @param Exception $ex
     * @return array|null
     */
    final public function handleException($ex)
    {
        $output = array_merge(array(
            'status' => 'error',
            'error' => 'unexpected_error',
            'code' => 'exception',
            'desc' => "exception:{$ex->getMessage()}@{$ex->getFile()}:{$ex->getLine()}",
            'profiler' => $this->profiler->fetch()
        ));
        $output['profiler']['systrace'] = $ex->getTraceAsString();
        $this->logger->emergency($output['desc']);
        if (defined('IN_SWOOLE'))
            return $output;
        $this->response->output($output);
        exit(0);
    }

    /**
     * @param $name
     * @return mixed
     * @throws InternalError
     * @throws ReflectionException
     */
    public function __get($name)
    {
        $name = ucfirst($name);
        $class = ucfirst(__NAMESPACE__ . "\\{$name}");
        if (!empty($this->components[$class])) {
            return $this->components[$class];
        } elseif (class_exists($class)) {
            $reflection = new ReflectionClass($class);
            $this->components[$class] = $reflection->newInstanceArgs([$this]);
            return $this->components[$class];
        } else
            throw new InternalError("component {$class} is not exist.");
    }
}