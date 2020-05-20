<?php

namespace Clover\Nano\Core\Redis;

use Exception;
use Clover\Nano\Core\App;
use Clover\Nano\Exception\InternalError;

/**
 * Class RedisBase
 *
 * @method psetex()
 * @method getSet()
 * @method randomKey()
 * @method renameKey()
 * @method renameNx()
 * @method expire($name, $expired)
 * @method exists(string $name)
 * @method del(string $name)
 * @method incr(string $name)
 * @method incrBy(string $name, int $value)
 * @method incrByFloat(string $name, float $value)
 * @method decr(string $name)
 * @method decrBy(string $name, int $value)
 * @method type()
 * @method append()
 * @method getRange()
 * @method setRange()
 * @method getBit()
 * @method setBit()
 * @method strlen()
 * @method getKeys()
 * @method sort()
 * @method sortAsc()
 * @method sortAscAlpha()
 * @method sortDesc()
 * @method sortDescAlpha()
 * @method lPush()
 * @method rPush()
 * @method lPushx()
 * @method rPushx()
 * @method lPop()
 * @method rPop()
 * @method blPop()
 * @method brPop()
 * @method lSize()
 * @method lRemove()
 * @method listTrim()
 * @method lGet()
 * @method lGetRange()
 * @method lSet()
 * @method lInsert()
 * @method sAdd()
 * @method sSize()
 * @method sRemove()
 * @method sMove()
 * @method sPop()
 * @method sRandMember()
 * @method sContains()
 * @method sMembers()
 * @method sInter()
 * @method sInterStore()
 * @method sUnion()
 * @method sUnionStore()
 * @method sDiff()
 * @method sDiffStore()
 * @method setTimeout()
 * @method save()
 * @method bgSave()
 * @method lastSave()
 * @method flushDB()
 * @method flushAll()
 * @method dbSize()
 * @method ttl()
 * @method pttl()
 * @method persist()
 * @method info()
 * @method resetStat()
 * @method move()
 * @method bgrewriteaof()
 * @method slaveof()
 * @method object()
 * @method bitop()
 * @method bitcount()
 * @method bitpos()
 * @method mset()
 * @method msetnx()
 * @method rpoplpush()
 * @method brpoplpush()
 * @method zAdd()
 * @method zDelete()
 * @method zRange()
 * @method zReverseRange()
 * @method zRangeByScore()
 * @method zRevRangeByScore()
 * @method zCount()
 * @method zDeleteRangeByScore()
 * @method zDeleteRangeByRank()
 * @method zCard()
 * @method zScore()
 * @method zRank()
 * @method zRevRank()
 * @method zInter()
 * @method zUnion()
 * @method zIncrBy()
 * @method expireAt()
 * @method pexpire()
 * @method pexpireAt()
 * @method hGet(string $name, string $field)
 * @method hSet(string $name, string $field, string $value)
 * @method hSetNx(string $name, string $field, string $value)
 * @method hDel(string $name, string $field)
 * @method hLen(string $name)
 * @method hKeys(string $name)
 * @method hVals(string $name)
 * @method hExists(string $name, string $field)
 * @method hIncrBy(string $name, string $field, int $number)
 * @method hIncrByFloat(string $name, string $field, float $number)
 * @method pipeline()
 * @method watch()
 * @method unwatch()
 * @method psubscribe()
 * @method unsubscribe()
 * @method punsubscribe()
 * @method time()
 * @method evalsha()
 * @method script()
 * @method dump()
 * @method getLastError()
 * @method clearLastError()
 * @method _prefix()
 */
class RedisBase
{
    private $hosts = [];
    private $connected = false;
    private $socket = null;
    private $mode = '';
    private $db = 0;
    private $authPass = '';
    private $timeout = 10;
    private $rwTimeout = 3;
    private $lastError = '';
    private $host = '';
    private $port = '';
    private $retry = 1;
    private $trans = false;
    private $transError = false;
    private $allowReconnect = true;
    private $bufferSize = 10240;
    private $persistent = false;
    private $errorSocket = [
        'no' => 0,
        'msg' => '',
    ];
    private $currentHost = '';
    private $readFnc = ['GETOPTION', 'TIME', 'EXISTS', 'GET', 'GETUNSERIALISE',
        'LASTSAVE', 'GETRANGE', 'STRLEN', 'HGET', 'HLEN', 'HKEYS', 'HVALS', 'HGETALL',
        'HEXISTS', 'LINDEX', 'LGET', 'LLEN', 'LSIZE', 'SCARD', 'SSIZE', 'SDIFF',
        'SCONTAINS', 'SISMEMBER', 'SMEMBERS', 'SGETMEMBERS', 'SUNION', 'ZCARD', 'ZSIZE',
        'ZCOUNT', 'ZRANGE', 'ZREVRANGE', 'ZRANGEBYSCORE', 'ZREVRANGEBYSCORE',
        'ZRANGEBYLEX', 'ZRANK', 'ZREVRANK', 'ZUNION'];
    /**
     * @var App
     */
    private $app;

    /**
     * RedisClient constructor.
     * @param App $app
     * @param array $hosts
     * @param string $authPass
     * @param int $db
     * @param int $timeout
     * @param int $rwTimeout
     * @param bool $allowReconnect
     * @param bool $persistent
     */
    final public function __construct(App $app, $hosts, $authPass, $db, $timeout = 10, $rwTimeout =
    0, $allowReconnect = true, $persistent = false)
    {
        $this->app = &$app;
        $this->hosts = $hosts;
        $this->authPass = $authPass;
        $this->timeout = (int)$timeout;
        $this->db = (int)$db;
        $this->persistent = (boolean)$persistent;
        $this->allowReconnect = $allowReconnect;
        if ((int)$rwTimeout != 0)
            $this->rwTimeout = (int)$rwTimeout;
    }

    final public function __destruct()
    {
        $this->close();
    }

    /**
     * @param bool $full
     */
    private function close($full = false)
    {
        $isResource = is_resource($this->socket);
        if (($this->persistent && $full && $isResource) || (!$this->persistent && $isResource))
            @fclose($this->socket);
        $this->socket = null;
        $this->connected = false;
        $this->currentHost = '';
        $this->errorSocket = ['no' => 0, 'msg' => ''];
    }

    /**
     * @return bool
     * @throws InternalError
     */
    public function ping()
    {
        return $this->__execmd('ping') === 'PONG' ? true : false;
    }

    /**
     * @return mixed
     * @throws InternalError
     */
    private function __execmd()
    {
        $args = func_get_args();
        $masterOnly = !in_array(strtoupper($args[0]), $this->readFnc, true);
        try {
            $this->lastError = '';
            if (!$this->connected)
                throw new InternalError("not yet connected to redis");
            elseif ($masterOnly && $this->mode !== 'master')
                throw new InternalError("need connect to master for write permission");
            $args[0] = strtoupper($args[0]);
            if ($this->__write($args)) {
                return $this->__read();
            } else
                $this->__errorHandle('Unable write to redis');
        } catch (InternalError $e) {
            if ($this->lastError !== '')
                $this->__errorHandle($this->lastError);
            else
                return $this->reconnect($masterOnly) === false ? false : call_user_func_array(
                    [$this, '__execmd'], $args);
        }
        return false;
    }

    /**
     * @param $arguments
     * @return bool|int
     * @throws InternalError
     */
    private function __write($arguments)
    {
        if (is_array($arguments)) {
            $raw = '';
            $reqlen = $this->__request($raw, $arguments);
            $raw = "*{$reqlen}\r\n{$raw}";
            return $this->__socketWrite($raw);
        } else
            return false;
    }

    /**
     * @param $raw
     * @param $arguments
     * @return int
     */
    private function __request(&$raw, $arguments)
    {
        $count = 0;
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $count += $this->__request($raw, $argument);
            } else {
                $count++;
                $arglen = strlen($argument);
                $raw .= "\${$arglen}\r\n{$argument}\r\n";
            }
        }
        return $count;
    }

    /**
     * @param $data
     * @param int $retry
     * @return bool|int
     * @throws InternalError
     */
    private function __socketWrite($data, $retry = 100)
    {
        $bytes_to_write = strlen($data);
        $bytes_written = 0;
        $retry = (int)$retry;
        if ($retry <= 0)
            $retry = 3;
        while ($bytes_written < $bytes_to_write) {
            if ($retry <= 0)
                return false;
            $rv = $bytes_written == 0 ? fwrite($this->socket, $data) : fwrite($this->socket,
                substr($data, $bytes_written));
            if ($rv === false || $rv == 0) {
                if ($rv === false) {
                    $this->lastError = 'Error while writing data to server.';
                    $this->__errorHandle($this->lastError);
                }
                return ($bytes_written == 0 ? false : $bytes_written);
            }
            $bytes_written += $rv;
            $retry--;
        }
        return $bytes_written;
    }

    /**
     * @param $desc
     * @throws InternalError
     */
    private function __errorHandle($desc)
    {
        $this->close(TRUE);
        throw new InternalError(trim($desc), "REDIS_ERROR");
    }

    /**
     * @param int $times
     * @return array|bool|null|string
     * @throws InternalError
     */
    private function __read($times = 0)
    {
        $response = $this->__receive($this->bufferSize);

        if (FALSE === $response || $response === '') {
            if (FALSE === $response) {
                $this->lastError = 'Error while reading line from the server.';
                $this->__errorHandle($this->lastError);
            }
            return FALSE;
        }

        $prefix = substr($response, 0, 1);
        $payload = substr($response, 1);

        switch ($prefix) {
            case '+':
                if (in_array(strtoupper($payload), ['OK', 'QUEUED'], true))
                    return true;
                return $payload;

            case '-':
                if ($this->trans)
                    $this->transError = true;
                $this->lastError = $payload;
                if (strtoupper(substr($this->lastError, 0, 3)) === 'ERR')
                    $this->lastError = substr($this->lastError, 4);
                if (empty($this->lastError))
                    $this->__errorHandle($this->lastError);
                return false;

            case ':':
                $integer = (int)$payload;
                return $integer == $payload ? $integer : $payload;

            case '$':
                $len = intval($payload);
                if ($len === -1)
                    return null;
                return $this->__receive($len + 3);

            case '*':
                $count = intval($payload);
                if ($count === -1)
                    return null;
                $multiResult = [];
                for ($i = 0; $i < $count; $i++)
                    $multiResult[] = $this->__read(++$times);
                return $multiResult;

            default:
                $this->lastError = "Unknown response prefix: '$prefix'.'";
                $this->__errorHandle($this->lastError);
                return null;
        }
    }

    /**
     * @param int $len
     * @return string
     */
    private function __receive($len = 4096)
    {
        $response = @fgets($this->socket, $len);
        $response = trim($response);
        return $response;
    }

    /**
     * Reconnect to redis if issue
     * @param bool $masterOnly
     * @return bool
     * @throws InternalError
     */
    private function reconnect($masterOnly = false)
    {
        try {
            if ($this->allowReconnect || ($this->mode !== 'master' && $masterOnly)) {
                for ($times = 0; $times <= $this->retry; $times++)
                    if ($this->connect($masterOnly) === true)
                        return true;
                return false;
            } elseif (!$this->connected)
                return $this->connect($masterOnly);
        } catch (InternalError $e) {
            $this->__errorHandle($e->getMessage());
        }
        return false;
    }

    /**
     * Connect to Redis
     * @param bool $masterOnly
     * @return bool
     * @throws InternalError
     */
    private function connect($masterOnly = false)
    {
        $this->__connect($masterOnly);
        if ($this->connected) {
            if (!empty($this->authPass))
                if (!$this->auth($this->authPass))
                    return false;
            if (!$this->select($this->db))
                return false;
        } else
            $this->__errorHandle("Connect {$this->currentHost} Failed, {$this->errorSocket['msg']}");
        return $this->connected;
    }

    /**
     * Connect Redis Any or master only
     * @param bool $masterOnly
     * @param int $retry
     * @return bool
     * @throws InternalError
     */
    public function __connect($masterOnly = false, $retry = 1)
    {
        if (is_array($this->hosts) && !empty($this->hosts)) {
            $hosts = $this->hosts;
            shuffle($hosts);
            foreach ($hosts as $key => $config) {

                if (is_array($config) && count($config) === 2 && isset($config[0]) && isset($config[1]) &&
                    (int)$config[1] !== 0
                ) {
                    if ($this->__openSocket($config[0], $config[1], $this->timeout, $this->
                    rwTimeout)
                    ) {
                        $role = $this->role();
                        $this->mode = $role[0];
                        if ($this->mode === 'master')
                            return true;
                        elseif ($this->mode !== 'master' && $masterOnly) {
                            for ($i = 0; $i < 10; $i++) {
                                if ($role[3] === 'connected') {
                                    $this->close();
                                    $this->mode = 'master';
                                    return $this->__openSocket($role[1], (int)$role[2], $this->timeout, $this->
                                    rwTimeout);
                                } else {
                                    usleep(500000);
                                    $role = $this->role();
                                }
                            }
                        } else
                            return true;
                    } else
                        continue;
                }
            }
            if ($retry > 0) {
                usleep(100000);
                return $this->__connect($masterOnly, (int)$retry - 1);
            } else
                return false;
        } else
            return false;
    }

    /**
     * @param string $host
     * @param int $port
     * @param int $timeout
     * @param int $rwTimeout
     * @return bool
     */
    private function __openSocket($host, $port, $timeout, $rwTimeout)
    {
        try {
            $this->close();
            $this->currentHost = "tcp://{$host}";
            $sTime = -$this->app->profiler->elapsed();
            $this->socket = $this->persistent ? pfsockopen($this->currentHost, $port, $this->errorSocket['no'], $this->errorSocket['msg'],
                $timeout) : fsockopen($this->currentHost, $port, $this->errorSocket['no'], $this->errorSocket['msg'], $timeout);
            $this->app->profiler->saveQuery("connect {$this->currentHost}", $sTime, "redis");
            if (!$this->socket || !is_resource($this->socket)) {
                $this->socket = null;
                return false;
            }
            if (!stream_set_timeout($this->socket, $rwTimeout, 0)) {
                $this->close(TRUE);
                return false;
            } else {
                $this->connected = true;
                $this->host = $host;
                $this->port = (int)$port;
                return true;
            }
        } catch (Exception $e) {
            $this->close();
            return false;
        }
    }

    /**
     * Get redis role
     * @return array|bool|null|string
     * @throws InternalError
     */
    private function role()
    {
        if (!$this->connected)
            return false;
        elseif ($this->__write(['ROLE'])) {
            return $this->__read();
        } else
            return false;
    }

    /**
     * Select Redis DB
     * @param string $pass
     * @return array|bool|null|string
     * @throws InternalError
     */
    private function auth($pass)
    {
        if (!$this->connected)
            return false;
        elseif ($this->__write(['AUTH', $pass])) {
            return $this->__read();
        } else
            return false;
    }

    /**
     * Select Redis DB
     * @param $db
     * @return array|bool|null|string
     * @throws InternalError
     */
    public function select($db)
    {
        $db = (int)$db;
        if (!$this->connected)
            return false;
        elseif ($this->__write(['SELECT', $db])) {
            $result = $this->__read();
            if ($result)
                $this->db = $db;
            return $result;
        } else
            return false;
    }

    /**
     * Start Redis Transaction
     * @return bool|mixed
     * @throws InternalError
     */
    public function multi()
    {
        if (!$this->trans) {
            $this->trans = $this->__execmd('multi');
            return $this->trans;
        } else
            return true;
    }

    /**
     * Commit Trans & Exec all command submit after multi block
     * @return bool
     * @throws InternalError
     */
    public function exec()
    {
        $results = null;
        if (!$this->transError)
            if (!$results = $this->__execmd('exec'))
                return false;
        $this->trans = false;
        $success = true;
        if (is_array($results) && !empty($results)) {
            foreach ($results as $result) {
                $result = is_numeric($result) ? (double)$result : $result;
                if ($result <= 0 && $result === true)
                    $success = false;
            }
        } else {
            return (bool)$results;
        }
        return $success;
    }

    /**
     * Discard or rollback the transaction
     * @return bool
     * @throws InternalError
     */
    public function discard()
    {
        if ($this->trans) {
            if ($this->__execmd('discard')) {
                $this->trans = false;
                $this->transError = false;
                return true;
            } else
                return false;
        } else
            return true;
    }

    /**
     * @param $name
     * @return mixed
     * @throws InternalError
     */
    public function getUnserialise($name)
    {
        return $this->varUnserialise($this->get($name));
    }

    /**
     * @param $Json
     * @return mixed
     */
    private function varUnserialise($Json)
    {
        $Data = json_decode($Json, true);
        return json_last_error() !== 0 ? $Json : $Data;
    }

    /**
     * @param $name
     * @return bool
     * @throws InternalError
     */
    public function get($name)
    {
        return $this->__execmd('get', $name);
    }

    /**
     * @param $name
     * @param $value
     * @param $expired
     * @return bool
     * @throws InternalError
     */
    public function setex($name, $value, $expired)
    {
        $expired = (int)$expired;
        return (boolean)$this->__execmd('setex', $name, $expired, $value);
    }

    /**
     * @param string $name
     * @param string $value
     * @param int $lifeTime
     * @param bool $noExists
     * @return bool
     */
    public function setSerialise($name, $value, $lifeTime = null, $noExists = null)
    {
        $value = $this->varSerialise($value);
        return (boolean)$this->set($name, $value, $lifeTime, $noExists);
    }

    /**
     * @param mixed $Data
     * @return string
     */
    private function varSerialise($Data)
    {
        return json_encode($Data);
    }

    /**
     * @param string $name Key Name
     * @param string $value Key Value
     * @param int $lifeTime Set key Life Time in second
     * @param bool $noExists Set only key not exists
     * @return mixed
     */
    public function set($name, $value, $lifeTime = null, $noExists = null)
    {
        $param = ['set', $name, $value];
        if (!empty($lifeTime))
            $param = array_merge($param, ['EX', (int)$lifeTime]);
        if (null !== $noExists) {
            $noExists = (boolean)$noExists;
            $param[] = $noExists ? 'NX' : 'XX';
        }
        $result = call_user_func_array([$this, '__execmd'], $param);
        return $result;
    }

    /**
     * @param $name
     * @param $args
     * @return mixed
     */
    public function __call($name, $args)
    {
        if (!is_array($args) || (is_array($args) && array_diff_key($args, array_keys(array_keys
                ($args))))
        )
            $args = [];
        array_unshift($args, $name);
        return call_user_func_array([$this, '__execmd'], $args);
    }

    /**
     * @param $name
     * @param mixed $value
     * @param null $expired
     * @return bool
     */
    public function setnxSerialise($name, $value, $expired = null)
    {
        $value = $this->varSerialise($value);
        return $this->setnx($name, $value, $expired);
    }

    /**
     * @param $name
     * @param $value
     * @param null $expired
     * @return bool
     */
    public function setnx($name, $value, $expired = null)
    {
        $expired = (int)$expired;
        return $this->set($name, $value, $expired, true);
    }

    /**
     * @param $name
     * @return array
     * @throws InternalError
     */
    public function hGetAll($name)
    {
        $data = (array)$this->__execmd('hgetall', $name);
        $result = [];
        $key = "";
        foreach ($data as $index => $value)
            if ($index % 2 === 0)
                $key = $value;
            else
                $result[$key] = $value;
        return $result;
    }
}