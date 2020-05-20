<?php

namespace Clover\Nano\Core;

use Clover\Nano\Core\Redis\RedisBase;
use Clover\Nano\Exception\InternalError;


/**
 * Class Redis
 *
 * @method psetex()
 * @method getSet()
 * @method randomKey()
 * @method renameKey()
 * @method renameNx()
 * @method expire(string $name, $second)
 * @method exists(string $name)
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
 * @method strlen(string $name)
 * @method sort()
 * @method sortAsc()
 * @method sortAscAlpha()
 * @method sortDesc()
 * @method sortDescAlpha()
 * @method lPush(string $name, string $value)
 * @method rPush(string $name, string $value)
 * @method lPushX(string $name, string $value)
 * @method rPushX(string $name, string $value)
 * @method lPop(string $name)
 * @method rPop(string $name)
 * @method bLPop(string $name)
 * @method bRPop(string $name)
 * @method lLen(string $name)
 * @method lRem(string $name, int $index, string $value)
 * @method lTrim(string $name, int $start, int $end)
 * @method lIndex(string $name, int $index)
 * @method lRange(string $name, int $start, int $end)
 * @method lSet(string $name, int $index, string $value)
 * @method lInsert()
 * @method sAdd()
 * @method sSize()
 * @method sRemove()
 * @method sMove()
 * @method sPop()
 * @method sRandMember()
 * @method sContains()
 * @method sMembers($name)
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
 * @method flushDB(int $dbIndex)
 * @method flushAll()
 * @method dbSize()
 * @method ttl(string $key)
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
 * @method expireAt(string $name, int $timestamp)
 * @method pExpire(string $name, int $milliSecond)
 * @method pExpireAt(string $name, int $milliSecondTimestamp)
 * @method hGet(string $name, string $field)
 * @method hSet(string $name, string $field, string $value)
 * @method hSetNx(string $name, string $field, string $value)
 * @method hDel(string $name, string $field)
 * @method hLen(string $name)
 * @method hKeys(string $name)
 * @method hVals(string $name)
 * @method hGetAll(string $name)
 * @method hExists(string $name, string $field)
 * @method hIncrBy(string $name, string $field, int $number)
 * @method hIncrByFloat(string $name, string $field, float $number)
 * @method hMset()
 * @method hMget()
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
 * @method get(string $name)
 * @method getUnserialise(string $name)
 * @method set(string $name, string $value, int $lifetime = 0, boolean $noExists = null)
 * @method setex(string $name, string $value, int $lifetime = 0)
 * @method setSerialise(string $name, mixed $value, int $lifetime = 0, boolean $noExists = null)
 * @method setnx(string $name, string $value, int $lifetime = 0)
 * @method setnxSerialise(string $name, mixed $value, int $lifetime = 0)
 * @method close()
 */
final class Redis
{
    /**
     * @var App
     */
    private $app;

    /**
     * @var RedisBase
     */
    private $redis = [];
    private $instance = [];
    private $size = 0;
    private $db = 0;
    private $timeout = 0;
    private $rwTimeout = 0;
    private $authPass = '';
    private $allowReconnect = true;
    private $persistent = false;
    private $restrictFnc = ['PING', 'SAVE', 'SCAN', 'OBJECT', 'CONNECT', 'OPEN',
        'PCONNECT', 'POPEN', 'AUTH', 'ECHO', 'BGREWRITEAOF', 'BGSAVE', 'CONFIG',
        'FLUSHALL', 'RESETSTAT', 'SLAVEOF', 'SLOWLOG', 'PEXPIRE', 'PEXPIREAT',
        'BITCOUNT', 'BITOP', 'PTTL', 'MIGRATE', 'SSCAN', 'ZSCAN', 'PSUBSCRIBE',
        'PUBLISH', 'SUBSCRIBE', 'PUBSUB', 'EVAL', 'EVALSHA', 'SCRIPT', 'GETLASTERROR',
        'CLEARLASTERROR', '_SERIALIZE', '_UNSERIALIZE', 'GETOPTION', 'DBSIZE', 'MGET',
        'GETMULTIPLE', 'GETMATCH', 'GETCOUNT', 'KEYS', 'GETKEYS', 'MULTI', 'WATCH',
        'EXEC'];
    private $hashRegex = "/\{(?<key>\w+)\}/";
    private $postHash = '';
    private $tempHash = '';

    /**
     * Redis constructor.
     * @param App $app
     * @throws InternalError
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $config = $app->config->get('redis');
        if (!$config['enable'])
            throw new InternalError('Redis is not enable.');
        $this->setPostHash($config->get('postHash'));
        if (!empty($config['instances']) && isset($config['db']) && !empty($config['timeout'])
            && isset($config['authPass']) && !empty($config['rwTimeout'])
        ) {
            $size = count($config['instances']);
            $range = range(0, $size - 1);
            $this->allowReconnect = (bool)$config['allowReconnect'];
            if (array_keys($config['instances']) === $range) {
                $this->persistent = (isset($config['persistent']) && $config['persistent']) ? true : false;
                $this->instances = $config['instances'];
                $this->size = $size;
                $this->db = (int)$config['db'];
                $this->authPass = (int)$config['authPass'];
                $this->timeout = (int)$config['timeout'];
                $this->rwTimeout = (int)$config['rwTimeout'];
            }
        }
    }

    /**
     * @param $postHash
     * @return bool
     */
    public function setPostHash($postHash)
    {
        $postHash = (string )$postHash;
        if (!empty($postHash)) {
            $this->postHash = substr($postHash, 0, 1) === "{" ? $postHash : "{{$postHash}}";
            return TRUE;
        } else
            return FALSE;
    }

    /**
     */
    public function __destruct()
    {
        if (!empty($this->redis)) {
            $profiler = &$this->app->profiler;
            $sTime = -$profiler->elapsed();
            foreach ($this->redis as $redis)
                if (method_exists($redis, '__destruct'))
                    $redis->__destruct();
            $this->redis = [];
            $profiler->saveQuery("close", $sTime, "redis");
        }
    }

    /**
     * @param $tempHash
     * @return bool
     */
    public function tempHash($tempHash)
    {
        $tempHash = (string )$tempHash;
        if (!empty($tempHash)) {
            $this->tempHash = substr($tempHash, 0, 1) === "{" ? $tempHash : "{{$tempHash}}";
            return TRUE;
        } else
            return FALSE;
    }

    /**
     * @param $db
     * @return bool
     * @throws InternalError
     */
    public function select($db)
    {
        $db = (int)$db;
        $success = true;
        foreach ($this->redis as $redis) {
            if ($redis instanceof RedisBase && !$redis->select($db))
                $success = false;
        }
        $this->db = $db;
        return $success;
    }

    /**
     * Sharding Redis Keys Performance
     * @param string $key
     * @return array
     */
    public function getkeys($key = '')
    {
        return $this->keys($key);
    }

    /**
     * Sharding Redis Keys Performance
     * @param string $key
     * @return mixed
     */
    public function keys($key = '')
    {
        if ($this->hasHash($key)) {
            return $this->__call('keys', [$key]);
        } else
            return false;
    }

    /**
     * Check is have hash in key
     * @param string $key
     * @return boolean
     */
    private function hasHash($key)
    {
        $matches = [];
        if (empty($key))
            return false;
        preg_match($this->hashRegex, $key, $matches);
        return !empty($matches) && !empty($matches['key']) ? true : false;
    }

    /**
     * @param string $name
     * @param array $args
     * @return bool|mixed
     */
    public function __call($name, $args)
    {
        $profiler = &$this->app->profiler;
        $sTime = -$profiler->elapsed();
        if (in_array($name, $this->restrictFnc, true))
            return false;
        $redis = null;
        $key = '';
        if (!empty($args))
            $key = $args[0];
        $index = $this->getIndex($key);
        if ($index !== false) {
            $redis = &$this->redis[$index];
            $ret = !empty($redis) ? call_user_func_array([$redis, $name], $args) : false;
        } else
            $ret = false;

        $rptArgs = '';
        foreach ($args as $arg)
            $rptArgs .= (" " . (is_string($arg) || is_numeric($arg) ? $arg : gettype($arg)));
        $profiler->saveQuery(sprintf("%s %s", $name, $rptArgs), $sTime, "redis");
        return $ret;
    }

    /**
     * @param string $key
     * @return int
     */
    private function getIndex($key = '')
    {
        if (!is_string($key))
            $key = '';
        $hash = $this->getHash($key . $this->postHash . $this->tempHash);
        $this->tempHash = '';
        $index = empty($hash) || empty($this->size) ? 0 : (int)bcmod(sprintf("%u", crc32
        ($hash)), $this->size);
        if (!isset($this->redis[$index])) {
            if (isset($this->instances[$index]))
                $this->redis[$index] = new RedisBase($this->app, $this->instances[$index], $this->authPass, $this->
                db, $this->timeout, $this->rwTimeout, $this->allowReconnect, $this->persistent);
            else
                return false;
        }
        return $index;
    }

    /**
     * Get Key Hash
     * @param string $key
     * @return string Hash
     */
    private function getHash($key)
    {
        $matches = [];
        if (empty($key))
            return '';
        preg_match($this->hashRegex, $key, $matches);
        return !empty($matches) && !empty($matches['key']) ? $matches['key'] : $key;
    }

    /**
     * getMultiple alias keys
     * @param string $key
     * @return array
     */
    public function getMultiple($key = '')
    {
        return $this->keys($key);
    }

    /**
     * Restate Shading Redis.
     */
    public function restate()
    {
        foreach ($this->redis as $index => $redis)
            unset($this->redis[$index]);
        usleep(100000);
    }

    /**
     * getMultiple alias keys
     * @param string|boolean $keys
     * @return int
     */
    public function del($keys)
    {
        if (is_array($keys)) {
            $deleteKeys = [];
            foreach ($keys as $key) {
                $index = $this->getIndex($key);
                if (!isset($deleteKeys[$index]))
                    $deleteKeys[$index] = [];
                $deleteKeys[$index][] = $key;
            }
            $success = 0;
            foreach ($deleteKeys as $deleteKey)
                $success += (int)$this->__call('del', $deleteKey);
            return $success;
        } else
            return (int)$this->__call('del', [$keys]);
    }
}