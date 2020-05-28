<?php

namespace Clover\Nano\Core\Db;

use mysqli_result;
use Clover\Nano\Core\App;
use Clover\Nano\Exception\DBQueryError;

class DbData
{
    /**
     * @var App
     */
    protected $app;

    /**
     * @var string
     */
    protected $colLiteral = '`';

    /**
     * @var string
     */
    protected $valLiteral = "'";

    /**
     * @var DbMysqli
     */
    protected $driver;

    /**
     * @var string
     */
    protected static $queryLock = '';

    /**
     * DbData constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->driver = new DbMysqli($app);
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return bool
     */
    final public function close()
    {
        return $this->driver->disconnect();
    }

    /**
     *
     */
    final public function tranStart()
    {
        $this->driver->tranStart();
    }

    /**
     *
     */
    final public function tranEnd()
    {
        $this->driver->tranEnd();
    }

    /**
     * @param string $mode
     */
    final public function queryLock($mode = 'FOR UPDATE')
    {
        self::$queryLock = $mode;
    }

    /**
     * @param string $mode
     */
    final public function queryMode($mode = 'store')
    {
        $this->driver->queryMode($mode);
    }

    /**
     * @param $tables
     * @param string $Mode
     * @throws DBQueryError
     */
    final public function lockTable($tables, $Mode = 'WRITE')
    {
        $Mode = ($Mode === 'WRITE' || $Mode === 'READ') ? $Mode : 'WRITE';
        $tableStr = '';
        if (is_array($tables) && !empty($tables)) {
            foreach ($tables as $table)
                $tableStr .= (!empty($tableStr) ? ", " : "") . $this->colLiteral . $this->driver->escapeStr($table) . "{$this->colLiteral} {$Mode}";
        } elseif (!empty($tables))
            $tableStr = $this->colLiteral . $this->driver->escapeStr($tables) . "{$this->colLiteral} {$Mode}";
        if (!empty($tableStr))
            $this->driver->query("LOCK TABLES {$tableStr}");
    }

    /**
     * @throws DBQueryError
     */
    public function unlockTable()
    {
        $this->driver->query("UNLOCK TABLES");
    }

    /**
     * @param $queryStr
     * @return array|bool|string[]
     * @throws DBQueryError
     */
    final public function special($queryStr)
    {
        $results = null;
        if (!empty($queryStr)) {
            $resResult = $this->driver->query($queryStr);
            if (NULL === $resResult || is_bool($resResult))
                $results = $resResult;
            else {
                switch ($this->driver->numRows($resResult)) {
                    case 0:
                        $results = null;
                        break;

                    case 1:
                        if ($this->driver->numFields($resResult) === 0)
                            $results = $this->driver->fetchCell(0, 0, $resResult);
                        else
                            $results = $this->driver->fetchAssoc($resResult);
                        break;

                    default:
                        while ($row = $this->driver->fetchAssoc($resResult))
                            $results[] = $row;
                }
                $this->driver->free($resResult);
            }
        }
        return $results;
    }

    /**
     * @param $queryStr
     * @return array
     * @throws DBQueryError
     */
    final public function multiQuery($queryStr)
    {
        return $this->driver->multiQuery($queryStr);
    }

    /**
     * @return int|string
     */
    final public function getInsertId()
    {
        return $this->driver->insertId();
    }

    /**
     * @param mysqli_result $resultSet
     * @return string[]|null
     */
    final public function fetchRow(mysqli_result $resultSet)
    {
        return $this->driver->fetchAssoc($resultSet);
    }

    /**
     * @param $table
     * @param $column
     * @param $key
     * @param $cond
     * @param $order
     * @param $limit
     * @param $group
     * @param $having
     * @return array
     * @throws DBQueryError
     */
    final public function all($table, $column = "*", $key = null, $cond = null, $order = null, $limit = null, $group = null, $having = null)
    {
        $results = [];
        $resResult = $this->select($table, $column, $cond, $order, $limit, $group, $having);

        if (!$key) {
            while ($row = $this->driver->fetchAssoc($resResult)) {
                $results[] = $row;
            }
        } else {
            while ($row = $this->driver->fetchAssoc($resResult)) {
                $results[$row[$key]] = $row;
            }
        }
        $this->driver->free($resResult);
        return $results;
    }

    /**
     * @param $table
     * @param $column
     * @param $cond
     * @param $order
     * @param $limit
     * @param $group
     * @param $having
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    final public function select($table, $column = "", $cond = null, $order = null, $limit = null, $group = null, $having = null)
    {
        $resultSet = $this->driver->query($this->getSelectSQL($table, $column, $cond, $order, $limit, $group, $having));
        self::$queryLock = '';
        return $resultSet;
    }

    /**
     * Get select sql
     * @param $table
     * @param string $column
     * @param $cond
     * @param $order
     * @param $limit
     * @param $group
     * @param $having
     * @return string
     */
    final public function getSelectSQL($table, $column = "*", $cond = null, $order = null, $limit = null, $group = null, $having = null)
    {
        if (!empty($table)) {
            $tableStr = $this->convertSplit($this->driver->escapeStr($table));
            $result = "SELECT " . $this->getColSQL($column) . " FROM {$this->colLiteral}{$tableStr}{$this->colLiteral}" .
                $this->getCondition($cond) . $this->getGroup($group, $having) . $this->getOrder($order) .
                $this->getLimit($limit) . " " . self::$queryLock;
            return $result;
        } else
            return "";
    }

    /**
     * @param $data
     * @return mixed
     */
    final private function convertSplit($data)
    {
        return str_replace([".", ","],
            ["{$this->colLiteral}.{$this->colLiteral}",
                "{$this->colLiteral},{$this->colLiteral}"], $data);
    }

    /**
     * @param $column
     * @param $alias
     * @return string
     */
    final private function getColSQL($column, $alias = true)
    {
        $columnStr = "";
        if (!empty($column)) {
            switch (gettype($column)) {
                case 'array':
                    foreach ($column as $ColAlias => $colName)
                        $columnStr .= (!empty($columnStr) ? ", " : "") . $this->getColNm($colName, $ColAlias, $alias);
                    break;

                case 'string';
                    $columnStr = $this->getColNm($column, null, $alias);
            }
        }
        return $columnStr;
    }

    /**
     * @param $colName
     * @param $colAlias
     * @param bool $alias
     * @param bool $Fnc
     * @return string
     */
    final private function getColNm($colName, $colAlias = null, $alias = true, $Fnc = false)
    {
        $result = '';
        $pattern = "/^\\{DB_([A-Z_]+)\\}/";
        if (preg_match("/^{$this->valLiteral}(.+){$this->valLiteral}$/", $colName, $matches))
            $result = $this->valLiteral . $this->driver->escapeStr($matches[1]) . $this->
                valLiteral;
        else {
            $colName = $this->convertSplit($colName);
            if (preg_match($pattern, $colName, $matches)) {
                $match = strtoupper($matches[1]);
                switch ($match) {
                    case 'DISTINCT':
                        $result = "DISTINCT " . $this->getColNm(str_replace($matches[0], "", $colName), null, false);
                        break;

                    case 'NO_CACHE':
                        $result = "SQL_NO_CACHE " . $this->getColNm(str_replace($matches[0], "", $colName), null,
                                false);
                        break;

                    case 'HIGH_PRIORITY':
                        $result = "HIGH_PRIORITY " . $this->getColNm(str_replace($matches[0], "", $colName), null,
                                false);
                        break;

                    case 'LOW_PRIORITY':
                        $result = "LOW_PRIORITY " . $this->getColNm(str_replace($matches[0], "", $colName), null,
                                false);
                        break;

                    case "MAX":
                        $colName = str_replace($matches[0], "", $colName);
                        $result = "MAX(" . $this->getColNm($colName, null, false) . ")";
                        break;

                    case "MIN":
                        $colName = str_replace($matches[0], "", $colName);
                        $result = "MIN(" . $this->getColNm($colName, null, false) . ")";
                        break;

                    case 'FNC':
                        $result = $this->getColNm(str_replace($matches[0], "", $colName), null, false, true);
                        break;

                    case 'VAL':
                        $result = "'" . $this->getColNm(str_replace($matches[0], "", $colName), null, false, true) .
                            "'";
                        break;

                    case "SUM":
                        $colName = str_replace($matches[0], "", $colName);
                        $result = "SUM(" . $this->getColNm($colName, null, false) . ")";
                        break;

                    case "TIME":
                    case "TIMESTAMP":
                        $colName = str_replace($matches[0], "", $colName);
                        $result = "TIME(" . $this->getColNm($colName, null, false) . ")";
                        break;

                    case "DATE":
                        $colName = str_replace($matches[0], "", $colName);
                        $result = "DATE(" . $this->getColNm($colName, null, false) . ")";
                        break;

                    case "DAY":
                        $colName = str_replace($matches[0], "", $colName);
                        $result = "DAY(" . $this->getColNm($colName, null, false) . ")";
                        break;

                    case "MONTH":
                        $colName = str_replace($matches[0], "", $colName);
                        $result = "MONTH(" . $this->getColNm($colName, null, false) . ")";
                        break;

                    case "YEAR":
                        $colName = str_replace($matches[0], "", $colName);
                        $result = "YEAR(" . $this->getColNm($colName, null, false) . ")";
                        break;

                    case "NOW":
                        $result = "NOW()";
                        break;

                    case "LENGTH":
                        $colName = str_replace($matches[0], "", $colName);
                        $result = "LENGTH(" . $this->getColNm($colName, null, false) . ")";
                        break;

                    case "COUNT":
                        $colName = str_replace($matches[0], "", $colName);
                        $result = ($colName === "*" ? "COUNT(*)" : "COUNT(" . $this->getColNm($colName, null, false) .
                            ")");
                        break;

                    case in_array($match, range('A', 'Z')):
                        $result = $this->getColNm(str_replace($matches[0], "", $colName), null, false);
                        break;

                    case "FROM_UNIXTIME":
                        $colName = str_replace($matches[0], "", $colName);
                        $result = "FROM_UNIXTIME(" . $this->getColNm($colName, null, false) . ")";
                        break;

                    case "FROM_UNIXTIME_DATE":
                        $colName = str_replace($matches[0], "", $colName);
                        $result = "FROM_UNIXTIME(" . $this->getColNm($colName, null, false) . ", \"%Y-%m-%d\")";
                        break;
                }
            } elseif ($Fnc === false && strpos($colName, $this->colLiteral) === false && $colName !== '*')
                $result = $this->colLiteral . $this->driver->escapeStr($colName) . $this->
                    colLiteral;
            else
                $result = $this->driver->escapeStr($colName);
            if ($alias)
                $result .= (!is_int($colAlias) && !empty($colAlias) ? " AS {$this->valLiteral}" .
                    $this->driver->escapeStr($colAlias) . $this->valLiteral : "");
        }
        return $result;
    }

    /**
     * @param array|string $cond
     * @return string
     */
    final private function getCondition($cond = null)
    {
        $condStr = '';
        $matches = [];
        if (!empty($cond)) {
            if (is_array($cond)) {
                $pattern = "/^\{DB_(OR|XOR|LB|RB|AND)\}$/i";
                $logic = true;
                $bracket = 0;
                $matches = [];
                foreach ($cond as $key => $value) {
                    if (is_string($value) && preg_match($pattern, $value, $matches)) {
                        switch (strtoupper($matches[1])) {
                            case "OR":
                                $condStr .= " OR ";
                                $logic = true;
                                break;

                            case "XOR":
                                $condStr .= " XOR ";
                                $logic = true;
                                break;

                            case "LB":
                                $condStr .= (!$logic ? " AND " : "") . "(";
                                $bracket++;
                                $logic = true;
                                break;

                            case "RB":
                                if ($bracket > 0) {
                                    $bracket--;
                                    $condStr .= ")";
                                }
                                break;

                            default:
                                $condStr .= " AND ";
                                $logic = true;
                                break;
                        }
                    } else {
                        if (!empty($key)) {
                            $condStr .= (!$logic ? " AND " : "") . $this->getCond($key, $value);
                            $logic = false;
                        }
                    }
                }
                $condStr = preg_replace("/(\s+)(AND|OR|XOR|\\()(\\s+)$/i", "", $condStr);
                if ($bracket > 0)
                    for ($i = $bracket; $i > 0; $i--)
                        $condStr .= ")";
            } elseif (preg_match_all("/({$this->colLiteral}?)([^\W]|[\w\d\-]+)({$this->colLiteral}?)(\s*)([>=<!]|LIKE|IS NOT NULL|IN|NOT IN|BETWEEN|NOT BETWEEN)(\s*)(({$this->valLiteral}([^{$this->valLiteral}]*){$this->valLiteral})|[\d.]|(\((.*)\))*)/i",
                    $cond, $matches) <= 0
            )
                $condStr = $cond;
        }
        return $cond !== null ? " WHERE {$condStr}" : "";
    }

    /**
     * @param $colName
     * @param $colValue
     * @return string
     */
    final private function getCond($colName, $colValue)
    {
        $condStr = '';
        switch (gettype($colValue)) {
            case 'array':
                $value = reset($colValue);
                $key = key($colValue);
                $condStr = $this->getColNm($colName);
                $result = [];
                switch (strtoupper($key)) {
                    case "LIST":
                    case "IN":
                        if (!is_array($value))
                            $value = [];
                        if (!empty($value))
                            foreach ($value as $k => $v)
                                $result[] = $this->driver->escapeStr($v);
                        $condStr .= " IN ({$this->valLiteral}" . implode("{$this->valLiteral},{$this->valLiteral}", $result) .
                            "{$this->valLiteral})";
                        break;

                    case "XLIST":
                    case "XIN":
                        if (!is_array($value))
                            $value = [];
                        if (!empty($value))
                            foreach ($value as $k => $v)
                                $result[] = $this->driver->escapeStr($v);
                        $condStr .= " NOT IN ({$this->valLiteral}" . implode("{$this->valLiteral},{$this->valLiteral}", $result) .
                            "{$this->valLiteral})";
                        break;

                    case "BETWEEN":
                        if (is_array($value) && count($value) === 2) {
                            $value = array_values($value);
                            $condStr .= " BETWEEN {$this->valLiteral}" . $this->driver->escapeStr($value[0]) .
                                "{$this->valLiteral} AND {$this->valLiteral}" . $this->driver->escapeStr($value[1]) .
                                $this->valLiteral;
                        }
                        break;

                    case "XBETWEEN":
                        if (is_array($value) && count($value) === 2) {
                            $value = array_values($value);
                            $condStr .= " NOT BETWEEN {$this->valLiteral}" . $this->driver->escapeStr($value[0]) .
                                "{$this->valLiteral} AND {$this->valLiteral}" . $this->driver->escapeStr($value[1]) .
                                $this->valLiteral;
                        }
                        break;
                    default:
                        $condStr = "";
                }
                break;

            case 'double':
            case 'integer':
            case "string":
            case 'boolean';
                $pattern = "/^\\{DB_([A-Z]+)\\}/";
                $Operator = " = ";
                if (is_bool($colValue))
                    $colValue = '';
                if (preg_match($pattern, $colValue, $matches)) {
                    switch (strtoupper($matches[1])) {
                        case "NE":
                            $Operator = " != ";
                            break;
                        case "GE":
                            $Operator = " >= ";
                            break;
                        case "GT":
                            $Operator = " > ";
                            break;
                        case "LE":
                            $Operator = " <= ";
                            break;
                        case "LT":
                            $Operator = " < ";
                            break;
                        case "NNE":
                            $Operator = " <=> ";
                            break;
                        case "LIKE":
                            $Operator = " LIKE ";
                            break;
                        case "XLIKE":
                            $Operator = "NOT LIKE ";
                            break;
                        case "INULL":
                            $Operator = " IS NULL";
                            break;
                        case "XINULL":
                            $Operator = " IS NOT NULL";
                            break;
                    }
                    if ($Operator !== " = ")
                        $colValue = str_replace($matches[0], "", $colValue);
                }
                $condStr = $this->getColNm($colName) . "{$Operator}";
                $condStr .= substr($Operator, -1) === " " ? $this->getColVal($colValue, $colName) :
                    "";
                break;

            case 'NULL':
                $condStr = $this->getColNm($colName) . " = null";
        }
        return $condStr;
    }

    /**
     * @param $colValue
     * @param $colName
     * @return string
     */
    final private function getColVal($colValue = null, $colName = null)
    {
        if ($colValue === null)
            return "NULL";
        else {
            $pattern = "/^\\{DB_([A-Z_]+)\\}/";
            if (is_array($colValue) || is_object($colValue))
                $colValue = json_encode($colValue);
            if (preg_match($pattern, $colValue, $matches)) {
                $colValue = $this->driver->escapeStr(str_replace($matches[0], "", $colValue));
                switch (strtoupper($matches[1])) {
                    case "YEAR":
                    case "MONTH":
                    case "DAY":
                    case "COUNT":
                    case "SUM":
                    case 'COL':
                        return $this->getColNm($colValue);

                    case 'FNC':
                        return $colValue;

                    case "NOW":
                        return "NOW()";

                    case "TIME":
                        return "CURRENT_TIME()";

                    case "INC":
                        $colValue = (double)$colValue;
                        return $this->getColNm($colName) . " + {$colValue}";
                        break;
                    case "DEC":
                        $colValue = (double)$colValue;
                        return $this->getColNm($colName) . " - {$colValue}";

                    case "TIMES":
                        $colValue = (double)$colValue;
                        return $this->getColNm($colName) . " * {$colValue}";

                    case "DIV":
                        $colValue = (double)$colValue;
                        return $this->getColNm($colName) . " / {$colValue}";

                    case "POWER":
                        $colValue = (double)$colValue;
                        return $this->getColNm($colName) . " ^ {$colValue}";
                }
            } else {
                $isNumber = is_int($colValue) || is_double($colValue) || is_float($colValue);
                return $isNumber ? $colValue : $this->valLiteral . $this->driver->escapeStr($colValue) . $this->valLiteral;
            }
        }
        return false;
    }

    /**
     * @param $group
     * @param $having
     * @return string
     */
    final private function getGroup($group = null, $having = null)
    {
        if (is_array($group))
            $group = array_values($group);
        $groupStr = $this->getColSQL($group);
        return !empty($groupStr) ? " GROUP BY {$groupStr}" . $this->getHaving($having) :
            "";
    }

    /**
     * @param $cond
     * @return string
     */
    final private function getHaving($cond = null)
    {
        return !empty($cond) ? " HAVING " . substr($this->getCondition($cond), 7) :
            "";
    }

    /**
     * @param $order
     * @return string
     */
    final private function getOrder($order = null)
    {
        $orderStr = '';
        if (!empty($order)) {
            if (is_array($order)) {
                foreach ($order as $key => $orderType) {
                    if (is_int($key)) {
                        if (in_array($orderType, ["{DB_RAND}", "RAND"]))
                            $key = 'RAND';
                        else {
                            $key = $orderType;
                            $orderType = "ASC";
                        }
                    } else {
                        $orderType = strtoupper($orderType);
                        if ($orderType != "DESC")
                            $orderType = "ASC";
                    }
                    if ($key === "RAND")
                        $orderStr .= (!empty($orderStr) ? ", " : "") . "RAND()";
                    else {
                        $key = $this->getColNm($key);
                        $orderStr .= (!empty($orderStr) ? ", " : "") . "{$key} {$orderType}";
                    }
                }
            } elseif (!empty($order))
                $orderStr = in_array(strtoupper($order), ["{DB_RAND}", "RAND"]) ? "RAND()" : $this->getColNm($order) .
                    " ASC";
        }
        return !empty($orderStr) ? " ORDER BY {$orderStr}" : "";
    }

    /**
     * @param $limit
     * @return string
     */
    final private function getLimit($limit = null)
    {
        $limitStr = '';
        if (!empty($limit)) {
            switch (gettype($limit)) {
                case "array":
                    $start = (int)reset($limit);
                    $limit = (int)next($limit);
                    if (!empty($limit))
                        $limitStr = "{$start}, {$limit}";
                    break;

                case "string":
                case "double":
                case "integer":
                    $limitStr = (int)$limit;
                    break;
            }
        }
        return !empty($limitStr) ? " LIMIT {$limitStr}" : "";
    }

    /**
     * @param $table
     * @param string $column
     * @param $cond
     * @param $order
     * @param $limit
     * @param $group
     * @param $having
     * @return mixed
     * @throws DBQueryError
     */
    final public function column($table, $column, $cond = null, $order = null, $limit = 0, $group = null, $having = null)
    {
        if (!empty($table) && !empty($column)) {
            $resResult = $this->select($table, $column, $cond, $order, $limit, $group, $having);
            if (!$resResult)
                return false;
            else {
                $results = [];
                $NumCol = $this->driver->numFields($resResult);
                while ($row = $this->driver->fetchRow($resResult)) {
                    if ($NumCol > 1)
                        $results[$row[0]] = $row[1];
                    else
                        $results[] = $row[0];
                }
                $this->driver->free($resResult);
                return $results;
            }
        } else
            return null;
    }

    /**
     * @param $table
     * @param string $column
     * @param $cond
     * @param $order
     * @param $seek
     * @param $group
     * @param $having
     * @return array|bool|null
     * @throws DBQueryError
     */
    final public function one($table, $column = "*", $cond = null, $order = null, $seek = 0, $group = null, $having = null)
    {
        if (!empty($table)) {
            return $this->driver->fetchAssoc($this->select($table, $column, $cond, $order,
                [$seek, 1], $group, $having));
        } else
            return null;
    }

    /**
     * @param $table
     * @param $column
     * @param $cond
     * @param $order
     * @param $seek
     * @return mixed
     * @throws DBQueryError
     */
    final public function cell($table, $column, $cond = null, $order = null, $seek = 0)
    {
        if (!empty($table) && !empty($column)) {
            return $this->driver->fetchCell(0, 0,
                $this->select($table, $column, $cond, $order, [$seek, 1]));
        } else
            return null;
    }

    /**
     * @param $table
     * @param $column
     * @param $cond
     * @param $group
     * @param $having
     * @return bool|float
     * @throws DBQueryError
     */
    final public function sum($table, $column, $cond = null, $group = null, $having = null)
    {
        if (!empty($table)) {
            if (is_array($column))
                $column = reset($column);
            return (double)$this->driver->fetchCell(0, 0, $this->select($table,
                "{DB_SUM}" . (!empty($column) ? $column : ""), $cond, null, 1, $group, $having));
        } else
            return false;
    }

    /**
     * Sql Bulk Insert
     * @param $table
     * @param $fields
     * @param $values
     * @param $ignore
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    final public function inserts($table, $fields, $values, $ignore = false)
    {
        if (!is_array($fields) || !is_array($values) || empty($fields) || empty($values))
            return false;

        if (!empty($table) && is_array($fields) && !empty($values)) {
            self::$queryLock = '';
            $tableStr = $this->convertSplit($this->driver->escapeStr($table));
            $iColField = '';
            $insertValues = '';
            $countField = 0;
            foreach ($fields as $colName) {
                $countField++;
                $iColField .= (!empty($iColField) ? ", " : "") . $this->getColNm($colName);
            }
            if (count($values) === 1)
                $values = [reset($values)];
            foreach ($values as $value) {
                if (!empty($value) && is_array($value) && count($value) === $countField) {
                    $insertValue = join(",", array_map(function ($v) {
                        return $this->getColVal($v);
                    }, $value));
                    $insertValues .= (!empty($insertValues) ? ", " : "") . "({$insertValue})";
                }
            }
            return (!empty($insertValues)) ? $this->driver->query("INSERT" . ((boolean)$ignore ? " IGNORE" : "") . " INTO {$this->colLiteral}{$tableStr}{$this->colLiteral} ({$iColField}) VALUES {$insertValues}")
                : false;
        } else
            return false;
    }

    /**
     * Insert/Update via condition
     * @param $table
     * @param $colData
     * @param $cond
     * @param $Check
     * @param $ignore
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    final public function action($table, $colData, $cond = null, $Check = false, $ignore = false)
    {
        if (!empty($cond) && $Check === (boolean)true)
            if ($this->count($table, $cond) === 0)
                $cond = null;
        self::$queryLock = '';
        if (!empty($cond))
            return $this->update($table, $colData, $cond, null, null, $ignore);
        else
            return $this->insert($table, $colData, $ignore);
    }

    /**
     * @param $table
     * @param $cond
     * @param $column
     * @param $group
     * @param $having
     * @return bool|int
     * @throws DBQueryError
     */
    final public function count($table, $cond = null, $column = null, $group = null, $having = null)
    {
        if (!empty($table)) {
            if (is_array($column))
                $column = reset($column);
            return (int)$this->driver->fetchCell(0, 0,
                $this->select($table,
                    "{DB_COUNT}" .
                    (!empty($column) ? $column : "*"), $cond, null, 1, $group, $having));
        } else
            return false;
    }

    /**
     * @param $table
     * @param $colDatas
     * @param $cond
     * @param $order
     * @param $limit
     * @param $ignore
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    final public function update($table, $colDatas, $cond = null, $order = null, $limit = null, $ignore = false)
    {
        if (!empty($table) && is_array($colDatas) && !empty($colDatas)) {
            self::$queryLock = '';
            $tableStr = $this->convertSplit($this->driver->escapeStr($table));
            $setCol = '';
            foreach ($colDatas as $colName => $colData)
                $setCol .= (!empty($setCol) ? ", " : "") . $this->getColNm($colName) . " = " . $this->
                    getColVal($colData, $colName);

            return $this->driver->query("UPDATE" . ((boolean)$ignore ? " IGNORE" : "") . " {$this->colLiteral}{$tableStr}{$this->colLiteral} SET {$setCol}" .
                $this->getCondition($cond) . $this->getOrder($order) . $this->getLimit($limit));
        } else
            return false;
    }

    /**
     * @param $table
     * @param $colDatas
     * @param $ignore
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    final public function insert($table, $colDatas, $ignore = false)
    {
        if (!empty($table) && is_array($colDatas) && !empty($colDatas)) {
            self::$queryLock = '';
            $tableStr = $this->convertSplit($this->driver->escapeStr($table));
            $setCol = '';
            foreach ($colDatas as $colName => $colData)
                $setCol .= (!empty($setCol) ? ", " : "") . $this->getColNm($colName) . " = " . $this->
                    getColVal($colData, $colName);
            if ($this->driver->query("INSERT" . ((boolean)$ignore ? " IGNORE" : "") . " INTO {$this->colLiteral}{$tableStr}{$this->colLiteral} SET {$setCol}")) {
                return $this->driver->insertId();
            } else {
                return false;
            }
        } else
            return false;
    }

    /**
     * @param $table
     * @param $colDatas
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    final public function insertUpdate($table, $colDatas)
    {
        if (!empty($table) && is_array($colDatas) && !empty($colDatas)) {
            self::$queryLock = '';
            $tableStr = $this->convertSplit($this->driver->escapeStr($table));
            $columnUpdate = '';
            $setCol = '';
            foreach ($colDatas as $colName => $colData) {
                $setCol .= (!empty($setCol) ? ", " : "") . $this->getColNm($colName) . " = " . $this->
                    getColVal($colData, $colName);
                $columnUpdate .= (!empty($columnUpdate) ? ", " : "") . "{$colName} = VALUES({$colName})";
            }
            return $this->driver->query("INSERT INTO {$this->colLiteral}{$tableStr}{$this->colLiteral} SET {$setCol} ON DUPLICATE KEY UPDATE {$columnUpdate}");
        } else
            return false;
    }

    /**
     * @param $table
     * @param $fields
     * @param $values
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    final public function insertsUpdate($table, $fields, $values)
    {
        if (!is_array($fields) || !is_array($values) || empty($fields) || empty($values))
            return false;

        if (!empty($table) && is_array($fields) && !empty($values)) {
            self::$queryLock = '';
            $tableStr = $this->convertSplit($this->driver->escapeStr($table));
            $iColField = '';
            $insertValues = '';
            $columnUpdate = '';
            $countField = 0;
            foreach ($fields as $colName) {
                $countField++;
                $colName = $this->getColNm($colName);
                $iColField .= (!empty($iColField) ? ", " : "") . $colName;
                $columnUpdate .= (!empty($columnUpdate) ? ", " : "") . "{$colName} = VALUES({$colName})";
            }
            if (count($values) === 1)
                $values = [reset($values)];
            foreach ($values as $value) {
                $insertValue = "";
                if (!empty($value) && is_array($value) && count($value) === $countField) {
                    foreach ($value as $val)
                        $insertValue .= (!empty($insertValue) ? ", " : "") . $this->getColVal($val);
                    $insertValues .= (!empty($insertValues) ? ", " : "") . "({$insertValue})";
                }
            }
            return (!empty($insertValues) && !empty($columnUpdate)) ? $this->driver->query("INSERT INTO {$this->colLiteral}{$tableStr}{$this->colLiteral} ({$iColField}) VALUES {$insertValues} ON DUPLICATE KEY UPDATE {$columnUpdate}")
                : false;
        } else
            return false;
    }

    /**
     * @param $table
     * @param $cond
     * @param $order
     * @param $limit
     * @return bool|int|mysqli_result
     * @throws DBQueryError
     */
    final public function delete($table, $cond = null, $order = null, $limit = null)
    {
        if (!empty($table)) {
            self::$queryLock = '';
            $tableStr = $this->convertSplit($this->driver->escapeStr($table));

            return $this->driver->query("DELETE FROM {$this->colLiteral}{$tableStr}{$this->colLiteral}" .
                $this->getCondition($cond) . $this->getOrder($order) . $this->getLimit($limit));
        } else
            return false;
    }

    /**
     * @param $name
     * @param $param
     * @param bool $fetchAll
     * @return array|bool
     * @throws DBQueryError
     */
    final public function procedure($name, $param = null, $fetchAll = false)
    {
        if (!empty($name)) {
            self::$queryLock = '';
            $result = null;
            $name = $this->driver->escapeStr($name);
            if (!empty($param)) {
                foreach ($param as $key => $PValue)
                    $param[$key] = $this->getColVal($PValue, $key);
                $this->driver->query("CALL {$name}(" . implode("','", $param) . ");");
            } else
                $this->driver->query("CALL {$name}();");
            $data = [];
            if ($fetchAll) {
                do {
                    if ($result = $this->driver->resultSetStore()) {
                        $dataNow = [];
                        while ($row = $this->driver->fetchAssoc($result))
                            $dataNow[] = $row;
                        $data[] = $dataNow;
                        $this->driver->free($result);
                    }
                } while ($this->driver->resultSetNext());
            } else {
                while ($row = $this->driver->fetchAssoc($result))
                    $data[] = $row;
                $this->driver->resultSetClear();
            }
            return $data;
        } else
            return false;
    }

    /**
     * @param $tableScr
     * @param $tableDes
     * @param $colData
     * @param $cond
     * @param $order
     * @param $limit
     * @param $group
     * @param $having
     * @param $ignore
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    final public function copy($tableScr, $tableDes, $colData, $cond = null, $order = null, $limit = null, $group = null, $having = null, $ignore = false)
    {
        if (!empty($tableScr) && !empty($tableDes) && !empty($colData) && is_array($colData)) {
            self::$queryLock = '';
            $tableScr = $this->convertSplit($this->driver->escapeStr($tableScr));
            $tableDes = $this->convertSplit($this->driver->escapeStr($tableDes));
            $colNameSrc = [];
            $colNameDes = [];
            foreach ($colData as $_ColNmSrc => $_ColNmDes) {
                $colNameSrc[] = $_ColNmSrc;
                $colNameDes[] = $_ColNmDes;
            }
            $colNameSrc = $this->getColSQL($colNameSrc, false);
            return $this->driver->query("INSERT" . ((boolean)$ignore ? " IGNORE" : "") . " INTO {$this->colLiteral}{$tableDes}{$this->colLiteral} ({$colNameSrc}) " .
                $this->getSelectSQL($tableScr, $colNameDes, $cond, $order, $limit, $group, $having));
        } else
            return false;
    }

    /**
     * @return bool
     */
    final public function affectedRow()
    {
        return $this->driver->affectedRow();
    }

    /**
     * @return bool|string
     */
    final public function error()
    {
        return $this->driver->error();
    }

    /**
     * @return bool|int
     */
    final public function errno()
    {
        return $this->driver->errorNo();
    }
}