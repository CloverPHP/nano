<?php

namespace Model;

use mysqli_result;
use Clover\Nano\Core\Common;
use Clover\Nano\Core\Model;
use Clover\Nano\Exception\DBQueryError;

/**
 * Class AccessLog
 * @package Model
 */
class AccessLog extends Model
{
    protected $prefix = '';
    protected $tableName = 'access_log';

    /**
     * @param array $params
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    public function addLog(array $params)
    {
        $data = [
            'service' => isset($params['service']) ? $params['service'] : '',
            'action' => isset($params['action']) ? $params['action'] : '',
            'status' => isset($params['status']) ? $params['status'] : '',
            'error' => isset($params['error']) ? $params['error'] : '',
            'code' => isset($params['code']) ? $params['code'] : '',
            'headers' => isset($params['headers']) ? $params['headers'] : null,
            'params' => isset($params['params']) ? $params['params'] : null,
            'result' => isset($params['result']) ? $params['result'] : null,
            'profiler' => isset($params['profiler']) ? $params['profiler'] : null,
            'timems' => isset($params['timems']) ? (double)$params['timems'] : '0',
            'clientip' => isset($params['clientip']) ? $params['clientip'] : '',
            'serverip' => isset($params['serverip']) ? $params['serverip'] : '',
            'created' => (int)Common::timestamp()
        ];
        return $this->addOne($data);
    }

    /**
     * @param array $params
     * @param int $page
     * @param int $size
     * @return array
     * @throws DBQueryError
     */
    public function getLogs(array $params, $page = 1, $size = 10)
    {
        $cond = $this->buildCond($params);
        if (!empty($page) && !empty($size))
            $limit = [($page - 1) * $size, $size];
        else
            $limit = $size;
        return $this->getAllByCond('*', null, $cond, ['id' => 'DESC'], $limit);
    }

    /**
     * @param array $params
     * @return bool|int
     * @throws DBQueryError
     */
    public function countLogs($params)
    {
        $cond = $this->buildCond($params);
        return $this->getCount('*', $cond);
    }

    /**
     * @param array $params
     * @return array|null
     */
    private function buildCond(array $params)
    {
        $cond = null;
        if (!empty($params['service'])) {
            $cond['service'] = $params['service'];
        }
        if (!empty($params['action'])) {
            $cond['action'] = $params['action'];
        }
        if (!empty($params['params'])) {
            $cond['params'] = "{DB_LIKE}%{$params['params']}%";
        }
        if (!empty($params['result'])) {
            $cond['result'] = "{DB_LIKE}%{$params['result']}%";
        }
        if (!empty($params['code'])) {
            $cond['code'] = "{DB_LIKE}%{$params['code']}%";
        }
        if (!empty($params['error'])) {
            $cond['error'] = "{DB_LIKE}%{$params['error']}%";
        }
        if (!empty($params['status'])) {
            $cond['status'] = $params['status'];
        }
        if (!empty($params['code'])) {
            $cond['code'] = $params['code'];
        }
        if (!empty($params['start'])) {
            $cond['{DB_A}created'] = "{DB_GE}{$params['start']}";
        }
        if (!empty($params['stop'])) {
            $cond['{DB_B}created'] = "{DB_LE}{$params['stop']}";
        }

        return $cond;
    }
}