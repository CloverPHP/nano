<?php


namespace Module;


use Clover\engins\Core\App;
use Clover\engins\Exception\Error;
use Clover\engins\Exception\UnexpectedError;

/**
 * Class Hook
 * Enhanced curl and make more easy to use
 */
class Hook
{

    /**
     * @var App
     */
    protected $app;

    /**
     * Hook constructor.
     * @param App $app
     */
    final public function __construct(App $app)
    {
        $this->app = $app;
        //
        $app->event->on('runtime_error', [$this, 'runtimeError']);
        $app->event->on('unexpected_error', [$this, 'unexceptionedError']);
        //
        $app->event->on('access_check', [$this, 'accessCheck']);
        $app->event->on('access_log', [$this, 'accessLog']);
        //
        $app->event->on('before_commit', [$this, 'beforeCommit']);
        $app->event->on('db_commit', [$this, 'dbCommit']);
        $app->event->on('commit_done', [$this, 'commitDone']);
        $app->event->on('commit_fail', [$this, 'commitFail']);
        $app->event->on('after_commit', [$this, 'afterCommit']);
        //
        $app->event->on('before_rollback', [$this, 'beforeRollback']);
        $app->event->on('db_rollback', [$this, 'dbRollback']);
        $app->event->on('rollback_done', [$this, 'rollbackDone']);
        $app->event->on('rollback_fail', [$this, 'rollbackFail']);
        $app->event->on('after_rollback', [$this, 'afterRollback']);
        //
        $app->event->on('before_output', [$this, 'afterAccess']);
        $app->event->on('after_output', [$this, 'afterAccess']);

    }

    /**
     * @param App $app
     * @param Error $ex
     */
    public function runtimeError(App $app, Error $ex)
    {
        $app->profiler->debug("hook:runtime_error");
    }

    /**
     * @param App $app
     * @param UnexpectedError $ex
     */
    public function unexceptionedError(App $app, UnexpectedError $ex)
    {
        $app->profiler->debug("hook:unexpected_error");
    }

    /**
     * @param App $app
     */
    public function accessCheck(App $app)
    {
        $app->profiler->debug("hook:access_check");
    }

    /**
     * @param App $app
     * @param array $output
     */
    public function accessLog(App $app, array &$output)
    {
        $app->profiler->debug("hook:access_log");
    }

    /**
     * @param App $app
     */
    public function beforeCommit(App $app)
    {
        $app->profiler->debug("hook:before_commit");
    }

    /**
     * @param App $app
     */
    public function dbCommit(App $app)
    {
        $app->profiler->debug("hook:db_commit");
    }

    /**
     * @param App $app
     */
    public function commitDone(App $app)
    {
        $app->profiler->debug("hook:commit_done");
    }

    /**
     * @param App $app
     */
    public function commitFail(App $app)
    {
        $app->profiler->debug("hook:commit_fail");
    }

    /**
     * @param App $app
     */
    public function afterCommit(App $app)
    {
        $app->profiler->debug("hook:after_commit");
    }

    /**
     * @param App $app
     */
    public function beforeRollback(App $app)
    {
        $app->profiler->debug("hook:before_rollback");
    }

    /**
     * @param App $app
     */
    public function dbRollback(App $app)
    {
        $app->profiler->debug("hook:db_rollback");
    }

    /**
     * @param App $app
     */
    public function rollbackDone(App $app)
    {
        $app->profiler->debug("hook:rollback_done");
    }

    /**
     * @param App $app
     */
    public function rollbackFail(App $app)
    {
        $app->profiler->debug("hook:rollback_fail");
    }

    /**
     * @param App $app
     */
    public function afterRollback(App $app)
    {
        $app->profiler->debug("hook:after_rollback");
    }
}