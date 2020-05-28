<?php

namespace Clover\Nano\Core;

use Clover\Nano\Core\Db\DbData;
use Clover\Nano\Exception\DBQueryError;

/**
 * Class Db
 * @package Core
 */
final class Db extends DbData
{
    /**
     * Db constructor.
     * @param App $app
     * @throws DBQueryError
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->connect();
    }

    /**
     * @param $group
     * @throws DBQueryError
     */
    private function connect($group = 'db')
    {
        $host = $this->app->config->get("$group.host", '');
        $sTime = -$this->app->profiler->elapsed();
        $this->driver->connect();
        $this->app->profiler->saveQuery("connect mysql://{$host}", $sTime, "db");

        if ($this->driver->connectErrorNo()) {
            $error = $this->driver->connectError();
            throw new DBQueryError("cb connect error: {$error}");
        }

        if ($this->app->config->get("$group.transaction", false))
            $this->tranStart();
    }

    /**
     * SQL Rollback
     * @return bool
     * @throws DBQueryError
     */
    public function rollback()
    {
        return $this->driver->rollback();
    }

    /**
     * DB is connected
     * @return bool
     */
    public function isConnected()
    {
        return $this->driver->isConnected();
    }

    /**
     * SQL Commit
     * @return bool
     * @throws DBQueryError
     */
    public function commit()
    {
        if ($this->errno() !== 0)
            throw new DBQueryError($this->error());
        return $this->driver->commit();
    }

    /**
     * Get last query string
     * @return mixed
     */
    public function getLastQuery()
    {
        return $this->driver->lastQuery;
    }
}