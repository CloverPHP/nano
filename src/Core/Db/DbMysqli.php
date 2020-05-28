<?PHP

namespace Clover\Nano\Core\Db;

use mysqli;
use mysqli_result;
use Clover\Nano\Core\App;
use Clover\Nano\Exception\DBQueryError;
use function mysqli_errno;
use function mysqli_error;
use function mysqli_field_count;
use function mysqli_real_query;
use function mysqli_store_result;
use function mysqli_use_result;

/**
 * Class DbMysqli
 * @property $connected
 * @property $lastQuery
 * @package Clover\Nano\Core\Db
 */
class DbMysqli
{

    /**
     * @var App
     */
    private $app;


    /**
     * @var bool
     */
    private $connected = false;

    /**
     * @var string
     */
    private $queryMode = 'store';

    /**
     * @var
     */
    private $resLink;

    /**
     * @var
     */
    private $resResult;

    /**
     * @var int
     */
    private $queryTime = 0;

    /**
     * @var string
     */
    private $errorMsg = '';

    /**
     * @var bool
     */
    private $queryError = false;

    /**
     * @var bool
     */
    private $tranEnable = false;

    /**
     * @var bool
     */
    private $tranStarted = false;

    /**
     * @var bool
     */
    private $persistent = false;

    /**
     * @var string
     */
    private $lastQuery = '';

    /**
     * DbMysqli constructor.
     * @param App $app
     */
    final public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * @param $name
     * @return mixed
     */
    final public function __get($name)
    {
        return isset($this->{$name}) ? isset($this->{$name}) : null;
    }


    /**
     *
     */
    final public function __destruct()
    {
        if ($this->connected)
            $this->disconnect();
    }

    /**
     * @return bool
     */
    final public function isConnected()
    {
        return $this->connected;
    }

    /**
     * @return bool
     */
    final public function disconnect()
    {
        if (!$this->connected)
            return false;
        $profiler = &$this->app->profiler;
        if ($this->isResLink($this->resLink)) {
            $sTime = -$profiler->elapsed();
            if (!$this->persistent) {
                $Thread_ID = @mysqli_thread_id($this->resLink);
                if ($Thread_ID) {
                    @mysqli_kill($this->resLink, $Thread_ID);
                }
            }
            @mysqli_close($this->resLink);
            $profiler->saveQuery("close", $sTime, "db");
            $this->resLink = null;
            $this->connected = false;
            return true;
        }
        return false;
    }

    /**
     * @param $resLink
     * @return bool
     */
    final private function isResLink($resLink)
    {
        return $resLink instanceof mysqli;
    }

    /**
     * @param  $group
     * @return mixed
     * @throws DBQueryError
     */
    final public function connect($group = 'db')
    {
        if (extension_loaded("mysqli") == false)
            throw new DBQueryError('Driver Mysqli is not exist.');

        $this->init(
            $this->app->config->get("$group.characterSet", 'UTF8MB4'),
            $this->app->config->get("$group.collate", 'UTF8MB4_UNICODE_CI'),
            $this->app->config->get("$group.timeOut", 10)
        );

        $port = (int)$this->app->config->get("$group.port", 3306);
        $host = (string)$this->app->config->get("$group.host", 'localhost');
        $this->persistent = (boolean)$this->app->config->get("db.persistent", false);
        $user = (string)$this->app->config->get("$group.user", 'root');
        $name = (string)$this->app->config->get("$group.name", '');
        $pass = (string)$this->app->config->get("$group.pass", '');
        if (!mysqli_real_connect(
            $this->resLink,
            $this->escapeStr($this->persistent ? "p:{$host}" : $host),
            $this->escapeStr($user),
            $this->escapeStr($pass),
            $this->escapeStr($name),
            $port)
        ) {
            $this->connected = false;
            throw new DBQueryError("DB Connect Failed : mysql://{$user}@{$host}:{$port}/{$name} " . $this->connectError());
        } else
            $this->connected = true;
        return $this->resLink;
    }

    /**
     * @param string $characterSet
     * @param string $collate
     * @param int $timeOut
     */
    final private function init($characterSet = 'UTF8MB4', $collate = 'UTF8MB4_UNICODE_CI', $timeOut = 1000)
    {
        $this->resLink = mysqli_init();
        $this->option(MYSQLI_OPT_CONNECT_TIMEOUT, $timeOut);
        $this->option(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        $this->option(MYSQLI_INIT_COMMAND, "SET AUTOCOMMIT = 0;");
        $this->option(MYSQLI_INIT_COMMAND, "SET NAMES '{$characterSet}' COLLATE '{$collate}';");
    }

    /**
     * @param $Option
     * @param $Data
     * @return bool
     */
    final public function option($Option, $Data)
    {
        return mysqli_options($this->resLink, $Option, $Data);
    }

    /**
     * @param $queryStr
     * @return mixed|string
     */
    final public function escapeStr($queryStr)
    {
        if (!$this->connected)
            return str_replace(["'", '`'], ["\\'", '\\`'], $queryStr);
        return mysqli_real_escape_string($this->resLink, $queryStr);
    }

    /**
     * @return string
     */
    final public function connectError()
    {
        return mysqli_connect_error();
    }

    /**
     * @return bool|string
     */
    final public function info()
    {
        if (!$this->connected)
            return false;
        return mysqli_get_host_info($this->resLink);
    }

    /**
     * @return bool
     */
    final public function ping()
    {
        if (!$this->connected)
            return false;
        return mysqli_ping($this->resLink);
    }

    /**
     * @param $name
     * @return bool
     */
    final public function selectDB($name)
    {
        if (!$this->connected)
            return false;
        return mysqli_select_db($this->resLink, $this->escapeStr($name));
    }

    /**
     *
     */
    final public function tranStart()
    {
        $this->tranEnable = true;
    }

    /**
     * @return bool
     */
    final public function isTranOn()
    {
        return $this->tranStarted;
    }

    /**
     *
     */
    final public function tranEnd()
    {
        $this->tranEnable = false;
    }

    /**
     * @param string $mode
     */
    final public function queryMode($mode = 'store')
    {
        $mode = strtolower($mode);
        if (in_array($mode, ['store', 'use'], true))
            $this->queryMode = $mode;
    }

    /**
     * @param array $queryStr
     * @return array
     * @throws DBQueryError
     */
    final public function multiQuery(array $queryStr)
    {
        $ret = [];
        foreach ($queryStr as $k => $sql) {
            $ret[$k] = $this->query($sql);
        }
        return $ret;
    }

    /**
     * @param $queryStr
     * @return bool|false|mysqli_result
     * @throws DBQueryError
     */
    final public function query($queryStr)
    {
        if (!$this->connected)
            return false;
        $this->resResult = false;
        if ($this->tranQuery($queryStr, false))
            return $this->realQuery($queryStr);
        else {
            $this->resResult = false;
            return $this->resResult;
        }

    }

    /**
     * @param $queryStr
     * @param bool $Multi
     * @return bool|false|mysqli_result
     * @throws DBQueryError
     */
    final public function tranQuery($queryStr, $Multi = false)
    {
        if (!empty($queryStr) && $this->tranEnable === true && $this->tranStarted === false) {
            $queries = ($Multi === true) ? $this->querySplit($queryStr) : [$queryStr];
            foreach ($queries as $query)
                if ((strtoupper(substr($query, 0, 6)) !== 'SELECT' && strtoupper(substr($query,
                            0, 3)) !== 'SET' && strtoupper(substr($query, 0, 5)) !== 'FLUSH') || strtoupper
                    (substr($query, -10)) === 'FOR UPDATE'
                ) {
                    $this->tranStarted = $this->realQuery("begin");
                    $this->queryError = !$this->tranStarted;
                    return $this->tranStarted;
                }
            return true;
        } else
            return true;
    }

    /**
     * @param $queryStr
     * @return array|mixed
     */
    final private function querySplit($queryStr)
    {
        $pattern = '%\s*((?:\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|/*[^*]*\*+([^*/][^*]*\*+)*/|\#.*|--.*|[^"\';#])+(?:;|$))%x';
        $matches = [];
        if (preg_match_all($pattern, $queryStr, $matches))
            return $matches[1];
        return [];
    }

    /**
     * @param $queryStr
     * @return bool|false|mysqli_result
     * @throws DBQueryError
     */
    final public function realQuery($queryStr)
    {
        if (!$this->connected)
            return false;
        $profiler = $this->app->profiler;
        $sTime = -$profiler->elapsed();
        $this->errorMsg = '';
        $result = mysqli_real_query($this->resLink, $queryStr);
        $this->resResult = $this->queryMode === 'use' ? mysqli_use_result($this->resLink) : mysqli_store_result($this->resLink);
        $this->resResult = (mysqli_field_count($this->resLink)) ? $this->resResult : $result;
        $this->queryTime = ceil((microtime(true) + $sTime) * 1000);
        $errNo = (int)mysqli_errno($this->resLink);

        if ($errNo !== 0) {
            $this->queryError = true;
            $this->errorMsg = mysqli_error($this->resLink);
            $queryStr = "Query error: {$this->errorMsg} - {$queryStr}";
            $profiler->saveQuery($queryStr, $sTime, "db");
            throw new DBQueryError($queryStr, $errNo);
        } else {
            $profiler->saveQuery($queryStr, $sTime, "db");
        }
        $this->lastQuery = $queryStr;
        return $this->resResult;
    }

    /**
     * @return bool|int
     */
    final public function affectedRow()
    {
        if (!$this->connected)
            return false;
        return mysqli_affected_rows($this->resLink);
    }

    /**
     * @return bool
     */
    final public function resultSetMore()
    {
        if (!$this->connected)
            return false;
        return mysqli_more_results($this->resLink);
    }

    /**
     * @return bool
     */
    final public function resultSetClear()
    {
        if (!$this->connected)
            return false;
        while ($this->resultSetNext())
            if ($resResult = $this->resultSetStore())
                $this->free($resResult);
        return true;
    }

    /**
     * @return bool
     */
    final public function resultSetNext()
    {
        if (!$this->connected)
            return false;
        return mysqli_next_result($this->resLink);
    }

    /**
     * @return bool|false|mysqli_result
     */
    final public function resultSetStore()
    {
        if (!$this->connected)
            return false;
        $this->resResult = mysqli_store_result($this->resLink);
        return $this->resResult;
    }

    /**
     * @param null $resResult
     * @return bool
     */
    final public function free($resResult = null)
    {
        if (!$this->connected)
            return false;
        $resResult = $this->getResResult($resResult);
        if ($this->isResResult($resResult)) {
            mysqli_free_result($resResult);
            if ($resResult === $this->resResult)
                $this->resResult = null;
            return true;
        } else
            return false;
    }

    /**
     * @param $resResult
     * @return mysqli_result
     */
    final private function getResResult($resResult)
    {
        return $resResult instanceof mysqli_result ? $resResult : $this->resResult;
    }

    /**
     * @param $resResult
     * @return bool
     */
    final private function isResResult($resResult)
    {
        return $resResult instanceof mysqli_result;
    }

    /**
     * @param null $resResult
     * @return bool|int
     */
    final public function numFields($resResult = null)
    {
        return $this->isResResult($resResult) ? mysqli_num_fields($resResult) : false;
    }

    /**
     * @param $SColumn
     * @param null $resResult
     * @return bool|false|object
     */
    final public function fetchField($SColumn, $resResult = null)
    {
        if ($this->isResResult($resResult)) {
            mysqli_field_seek($resResult, $SColumn);
            $FInfo = mysqli_fetch_field($resResult);
            return $FInfo;
        } else
            return false;
    }

    /**
     * @param int $Cols
     * @param int $Rows
     * @param null $resResult
     * @return bool
     */
    final public function fetchCell($Cols = 0, $Rows = 0, $resResult = null)
    {
        $resResult = $this->getResResult($resResult);
        if ($this->isResResult($resResult)) {
            mysqli_data_seek($resResult, $Rows);
            $Result = mysqli_fetch_row($resResult);
            return $Result[$Cols];
        } else
            return false;
    }

    /**
     * @param null $resResult
     * @return array|null
     */
    final public function fetchRow($resResult = null)
    {
        return $this->isResResult($resResult) ? mysqli_fetch_row($resResult) : null;
    }

    /**
     * @param null $resResult
     * @return string[]|null
     */
    final public function fetchAssoc($resResult = null)
    {
        return $this->isResResult($resResult) ? mysqli_fetch_assoc($resResult) : null;
    }

    /**
     * @param null $resResult
     * @return bool|int
     */
    final public function numRows($resResult = null)
    {
        return $this->isResResult($resResult) ? mysqli_num_rows($resResult) : false;
    }

    /**
     * @param $Row
     * @param null $resResult
     * @return bool
     */
    final public function seek($Row, $resResult = null)
    {
        return $this->isResResult($resResult) ? mysqli_data_seek($resResult, $Row) : false;
    }

    /**
     * @return int|string
     */
    final public function insertId()
    {
        return mysqli_insert_id($this->resLink);
    }

    /**
     * @return bool|false|mysqli_result
     * @throws DBQueryError
     */
    final public function rollback()
    {
        if (!$this->connected)
            return false;
        $this->tranStarted = false;
        return $this->realQuery("rollback");
    }

    /**
     * @return bool|false|mysqli_result
     * @throws DBQueryError
     */
    final public function commit()
    {
        if (!$this->connected)
            return false;
        if ($this->tranStarted) {
            $this->tranStarted = false;
            return $this->realQuery("commit");
        } else
            return true;
    }

    /**
     * @return int
     */
    final public function connectErrorNo()
    {
        return mysqli_connect_errno();
    }

    /**
     * @return bool|int
     */
    final public function errorNo()
    {
        if (!$this->connected)
            return false;
        return mysqli_errno($this->resLink);
    }

    /**
     * @return bool|string
     */
    final public function error()
    {
        if (!$this->connected)
            return false;
        return mysqli_error($this->resLink);
    }
}