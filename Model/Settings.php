<?php

namespace Model;

use mysqli_result;
use Clover\Nano\Core\Model;
use Clover\Nano\Exception\DBQueryError;

/**
 * Class Setting
 * @package Model
 */
class Settings extends Model
{
    protected $prefix = '';
    protected $tableName = "setting";

    /**
     * @param string $name
     * @param string|array $value
     * @param int|null $status
     * @return bool|int|mysqli_result
     * @throws DBQueryError
     */
    public function updateSetting($name, $value, $status = null)
    {
        $newData = [];
        if ($status !== null) $newData['status'] = $status;
        $data = $this->getOneByCond('*', ['name' => $name]);
        if ($data) {
            if ($data['type'] === 'json')
                $value = json_encode($value);
            $newData['data'] = $value;
            return $this->updateOneByCond($newData, ['name' => $name]);
        } else {
            $newData = ['name' => $name];
            $newData['status'] = $status === null ? 1 : (int)$status;
            if (is_array($value) || is_object($value)) {
                $newData['type'] = 'json';
                $newData['data'] = json_encode($value);
            } else {
                $newData['type'] = is_numeric($value) ? 'number' : (is_bool($value) ? 'bool' : 'string');
            }
            return $this->addOne($newData);
        }
    }

    /**
     * @param int $merchantId
     * @return array
     * @throws DBQueryError
     */
    final public function load($merchantId = 0)
    {
        $setting = [];
        $this->processSetting($this->getAllByCond('*', null, [
            "status" => 1,
            "merchantid" => $merchantId === 0 ? $merchantId : ['in' => [0, $merchantId]]
        ], ["name" => "ASC", "merchantid" => "DESC"]), $setting);
        return $setting;
    }

    /**
     * Process Setting
     * @param array $data
     * @param array $setting
     */
    private function processSetting(array $data, array &$setting)
    {
        if (is_array($data) && !empty($data)) {
            foreach ($data as $row) {
                if (isset($setting[$row['name']]))
                    continue;
                switch ($row['type']) {
                    case 'boolean':
                        $row['data'] = (boolean)$row['data'];
                        break;

                    case 'string':
                        $row['data'] = (string)$row['data'];
                        break;

                    case 'number':
                        $row['data'] = (double)$row['data'];
                        break;

                    case 'json':
                        $row['data'] = json_decode($row['data'], true);
                        break;
                }
                $setting[$row['name']] = $row['data'];
            }
        }
    }

    /**
     * @param array $params
     * @return bool|mysqli_result
     * @throws DBQueryError
     */
    public function addSetting($params)
    {
        $data = [
            'name' => isset($params['name']) ? $params['name'] : '',
            'type' => isset($params['type']) ? $params['type'] : 'string',
            'data' => isset($params['data']) ? $params['data'] : '',
            'status' => isset($params['status']) ? $params['status'] : '1'
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
    public function getSettings($params, $page = 1, $size = 10)
    {
        $cond = $this->buildCond($params);
        if (!empty($page) && !empty($size))
            $limit = [($page - 1) * $size, $size];
        else
            $limit = $size;
        return $this->getAllByCond('*', null, $cond, ['id' => 'asc'], $limit);
    }

    /**
     * @param array $params
     * @return bool|int
     * @throws DBQueryError
     */
    public function countSettings($params)
    {
        $cond = $this->buildCond($params);
        return $this->getCount('*', $cond);
    }

    /**
     * @param array $params
     * @return array|null
     */
    private function buildCond($params)
    {
        $cond = null;
        if (!empty($params['status'])) {
            $cond['status'] = "{$params['status']}";
        }
        return $cond;
    }
}