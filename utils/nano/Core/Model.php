<?php

namespace Clover\Nano\Core;

use Clover\Nano\Exception\DBQueryError;
use mysqli_result;

/**
 * Class Model
 * @package Clover\Nano\Core
 */
abstract class Model
{
    /**
     * @var App
     */
    protected $app;

    /**
     * @var Db
     */
    protected $db;

    /**
     * @var string
     */
    protected $tableName = '';

    /**
     * @var string
     */
    protected $dbName = '';

    /**
     * @var string|null
     */
    protected $prefix = null;

    /**
     * Model constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        if ($this->prefix === null)
            $this->prefix = $this->tableName . "_";
        $this->db = &$this->app->db;
        $this->prefix = (string)$this->prefix;
    }

    /**
     * @param string $dbNames
     */
    public function setDbName($dbNames = '')
    {
        if (is_string($dbNames) && !empty($dbNames))
            $this->dbName = $dbNames;
    }

    /**
     * @return bool|int|string
     */
    public function getLastQuery()
    {
        return $this->db->getLastQuery();
    }

    /**
     * @return bool|int|string
     */
    protected function getLastInsertId()
    {
        return $this->db->getInsertId();
    }

    /**
     * @param array $params
     * @param bool $ignore
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    protected function addOne(array $params, $ignore = false)
    {
        $data = $this->buildOne($params);
        $ret = $this->db->insert($this->getTableName(), $data, $ignore);

        if (false === $ret) {
            return false;
        } else {
            $id = $this->db->getInsertId();
            return $id;
        }
    }

    /**
     * @param $param
     * @return array
     */
    private function buildOne($param)
    {
        $data = [];
        foreach ($param as $k => $v) {
            $data["{$this->prefix}$k"] = $v;
        }
        return $data;
    }

    /**
     * @return string
     */
    private function getTableName()
    {
        return !empty($this->dbName) ? "{$this->dbName}.{$this->tableName}" : $this->tableName;
    }

    /**
     * @param array $fields
     * @param array $params
     * @param bool $ignore
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    protected function addMulti(array $fields, array $params, $ignore = false)
    {
        $fields = $this->buildField($fields);
        $ret = $this->db->inserts($this->getTableName(), $fields, $params, $ignore);

        if (false === $ret) {
            return false;
        } else {
            $id = $this->db->getInsertId();
            return $id;
        }
    }

    /**
     * @param $param
     * @return array
     */
    private function buildField($param)
    {
        foreach ($param as $k => &$v)
            $v = "{$this->prefix}$v";
        return $param;
    }

    /**
     * @param $id
     * @param string|array $field
     * @param null $orderBy
     * @param int $seek
     * @param null $groupBy
     * @param null $having
     * @return array
     * @throws DBQueryError
     */
    protected function getOneById($field, $id, $orderBy = null, $seek = 0, $groupBy = null, $having = null)
    {
        $data = $this->db->one(
            $this->getTableName(),
            $this->buildSelCol($field),
            $this->buildIdField($id),
            $this->buildOrder($orderBy),
            $seek,
            $this->buildGroup($groupBy),
            $this->buildCond($having)
        );
        return !is_array($field) ? $this->formatOne($data) : $data;
    }

    /**
     * @param $fields
     * @return array|bool|string
     */
    private function buildSelCol($fields)
    {
        $patternFnc = '/^\\{DB_FNC\\}(.*)/';
        $matches = [];
        if ($fields === '*')
            return $fields;
        elseif (is_array($fields) && !empty($fields)) {
            $result = [];
            foreach ($fields as $k => $v) {
                if (is_numeric($k))
                    $k = $v;
                $result[$k] = preg_match($patternFnc, $v, $matches) ? $v : $this->buildCmd($v);
            }
            return $result;
        } elseif (is_string($fields)) {
            return preg_match($patternFnc, $fields, $matches) ? $fields : $this->buildCmd($fields);
        } else
            return false;
    }

    /**
     * @param $value
     * @param bool $prefix
     * @return mixed
     */
    private function buildCmd($value, $prefix = true)
    {
        $matches = [];
        if (is_string($value) && !is_numeric($value)) {
            if (preg_match("/^({DB_([A-Z]|FNC|SUM|COL|COUNT|DISTINCT|MIN|MAX|RAND|DAY|MONTH|YEAR)})(.*)/", $value, $matches)) {
                $value = (strtoupper($matches[2]) === 'RAND') ? "{DB_RAND}" : "{$matches[1]}" . ($prefix ? $this->prefix : "") . "{$matches[3]}";
            } elseif ($prefix)
                $value = "{$this->prefix}{$value}";
        }
        return $value;
    }

    /**
     * Return Id Field
     * @param $id
     * @return array
     */
    private function buildIdField($id)
    {
        return $cond = [
            "{$this->prefix}id" => $id,
        ];
    }

    /**
     * @param mixed $orders
     * @return mixed
     */
    private function buildOrder($orders)
    {
        if (is_array($orders) && !empty($orders)) {
            $data = [];
            foreach ($orders as $k => $v)
                if (is_numeric($k)) {
                    if (in_array($v, ['{DB_RAND}', 'RAND']))
                        $data[$k] = 'RAND';
                    else
                        $data[$this->buildCmd($v)] = 'ASC';
                } else {
                    $v = strtoupper($v);
                    if ($v != "DESC")
                        $v = "ASC";
                    $data[$this->buildCmd($k)] = $v;
                }
            return $data;
        } elseif (in_array($orders, ['{DB_RAND}', 'RAND']))
            return 'RAND';
        else
            return $this->buildCmd($orders);
    }

    /**
     * @param $groupBy
     * @return mixed
     */
    private function buildGroup($groupBy)
    {
        if (is_array($groupBy) && !empty($groupBy)) {
            $data = [];
            foreach ($groupBy as $v)
                $data[] = $this->buildCmd($v);
            return $data;
        } elseif (is_string($groupBy)) {
            return $this->buildCmd($groupBy);
        } else
            return $groupBy;
    }

    /**
     * @param mixed $cond
     * @return mixed
     */
    private function buildCond($cond)
    {
        if (is_array($cond) && !empty($cond)) {
            $data = [];
            foreach ($cond as $k => $v)
                $data[$this->buildCmd($k)] = $this->buildCmd($v, false);
            return $data;
        } elseif (is_string($cond)) {
            return $this->buildCmd($cond);
        } else
            return $cond;
    }

    /**
     * @param $param
     * @return array
     */
    private function formatOne($param)
    {
        $data = [];
        if ($param) {
            foreach ($param as $k => $v) {
                $data[substr($k, strlen($this->prefix))] = $v;
            }
        }
        return $data;
    }

    /**
     * @param $field
     * @param $id
     * @param null $orderBy
     * @param int $seek
     * @return mixed
     * @throws DBQueryError
     */
    protected function getCellById($field, $id, $orderBy = null, $seek = 0)
    {
        $result = $this->db->cell(
            $this->getTableName(),
            $this->buildSelCol($field),
            $this->buildIdField($id),
            $this->buildOrder($orderBy),
            $seek
        );
        return $result;
    }

    /**
     * @param string $field
     * @param null $cond
     * @param null $groupBy
     * @param null $having
     * @return bool|int
     * @throws DBQueryError
     */
    protected function getSum($field = '*', $cond = null, $groupBy = null, $having = null)
    {
        return $this->db->sum(
            $this->getTableName(),
            $this->buildSelCol($field),
            $this->buildCond($cond),
            $this->buildGroup($groupBy),
            $this->buildCond($having)
        );
    }

    /**
     * @param string $field
     * @param null $cond
     * @param null $groupBy
     * @param null $having
     * @return bool|int
     * @throws DBQueryError
     */
    protected function getCount($field = '*', $cond = null, $groupBy = null, $having = null)
    {
        return $this->db->count(
            $this->getTableName(),
            $this->buildCond($cond),
            $this->buildSelCol($field),
            $this->buildGroup($groupBy),
            $this->buildCond($having)
        );
    }

    /**
     * @param $id
     * @param null $orderBy
     * @param int $limit
     * @return bool|int|mysqli_result
     * @throws DBQueryError
     */
    protected function deleteOneById($id, $orderBy = null, $limit = 0)
    {
        return $this->db->delete($this->getTableName(), $this->buildIdField($id), $this->buildOrder($orderBy), $limit);
    }

    /**
     * @param array $data
     * @param $id
     * @param bool $ignore
     * @return bool|int|mysqli_result
     * @throws DBQueryError
     */
    protected function updateOneById(array $data, $id, $ignore = false)
    {
        return $this->db->update($this->getTableName(), $this->buildOne($data), $this->buildIdField($id), null, 1, $ignore);
    }

    /**
     * @param string $field
     * @param array $cond
     * @param null $orderBy
     * @param int $seek
     * @param null $groupBy
     * @param null $having
     * @return array
     * @throws DBQueryError
     */
    protected function getOneByCond($field = '*', array $cond = null, $orderBy = null, $seek = 0, $groupBy = null, $having = null)
    {
        $data = $this->db->one(
            $this->getTableName(),
            $this->buildSelCol($field),
            $this->buildCond($cond),
            $this->buildOrder($orderBy),
            $seek,
            $this->buildGroup($groupBy),
            $this->buildCond($having)
        );

        return !is_array($field) ? $this->formatOne($data) : $data;
    }

    /**
     * @param string $field
     * @param array $cond
     * @param null $orderBy
     * @param int $limit
     * @param null $groupBy
     * @param null $having
     * @return array
     * @throws DBQueryError
     */
    protected function getColumnByCond($field = '*', array $cond = null, $orderBy = null, $limit = 0, $groupBy = null, $having = null)
    {
        return $this->db->column(
            $this->getTableName(),
            $this->buildSelCol($field),
            $this->buildCond($cond),
            $this->buildOrder($orderBy),
            $limit,
            $this->buildGroup($groupBy),
            $this->buildCond($having)
        );
    }

    /**
     * @param $field
     * @param array $cond
     * @param null $orderBy
     * @param int $seek
     * @return mixed
     * @throws DBQueryError
     */
    protected function getCellByCond($field, array $cond = [], $orderBy = null, $seek = 0)
    {
        $result = $this->db->cell(
            $this->getTableName(),
            $this->buildSelCol($field),
            $this->buildCond($cond),
            $this->buildOrder($orderBy),
            $seek
        );
        return $result;
    }

    /**
     * @param string $field
     * @param string $key
     * @param array $cond
     * @param null $orderBy
     * @param null $limit
     * @param null $groupBy
     * @param null $having
     * @return array
     * @throws DBQueryError
     */
    protected function getAllByCond($field = '*', $key = null, array $cond = null, $orderBy = null, $limit = null, $groupBy = null, $having = null)
    {
        $key = is_null($key) ? $key : ($field === '*' ? $this->prefix . $key : $key);
        $data = $this->db->all(
            $this->getTableName(),
            $this->buildSelCol($field),
            $key,
            $this->buildCond($cond),
            $this->buildOrder($orderBy),
            $limit,
            $this->buildGroup($groupBy),
            $this->buildCond($having)
        );
        return !is_array($field) ? $this->formatAll($data) : $data;
    }

    /**
     * @param $params
     * @return array
     */
    private function formatAll($params)
    {
        $data = [];
        if ($params) {
            foreach ($params as $index => $param) {
                $data[$index] = [];
                if ($param) {
                    foreach ($param as $k => $v) {
                        $data[$index][substr($k, strlen($this->prefix))] = $v;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * @param $cond
     * @param null $orderBy
     * @param int $limit
     * @return bool|int|mysqli_result
     * @throws DBQueryError
     */
    protected function deleteOneByCond($cond, $orderBy = null, $limit = 0)
    {
        return $this->db->delete($this->getTableName(), $this->buildCond($cond), $this->buildOrder($orderBy), $limit);
    }

    /**
     * @param $cond
     * @param null $orderBy
     * @param null $limit
     * @return bool|int|mysqli_result
     * @throws DBQueryError
     */
    protected function deleteAll($cond = null, $orderBy = null, $limit = null)
    {
        return $this->db->delete($this->getTableName(), $this->buildCond($cond), $this->buildOrder($orderBy), $limit);
    }

    /**
     * @param array $data
     * @param array $cond
     * @param $orderBy
     * @param bool $ignore
     * @return bool|int|mysqli_result
     * @throws DBQueryError
     */
    protected function updateOneByCond(array $data, array $cond, $orderBy = null, $ignore = false)
    {
        return $this->db->update($this->getTableName(), $this->buildOne($data), $this->buildCond($cond), $this->buildOrder($orderBy), 1, $ignore);
    }

    /**
     * @param array $data
     * @param array $cond
     * @param null $orderBy
     * @param null $limit
     * @param bool $ignore
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    protected function updateAll(array $data, array $cond, $orderBy = null, $limit = null, $ignore = false)
    {
        return $this->db->update($this->getTableName(), $this->buildOne($data), $this->buildCond($cond), $this->buildOrder($orderBy), $limit, $ignore);
    }

    /**
     * @param array $data
     * @param array $cond
     * @param bool $check Check those condition exist or not
     * @param bool $ignore
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    protected function createUpdate(array $data, array $cond, $check = false, $ignore = false)
    {
        return $this->db->action($this->getTableName(), $this->buildOne($data), $this->buildCond($cond), $check, $ignore);
    }
}
