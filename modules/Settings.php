<?php

namespace Module;

use mysqli_result;
use Clover\Nano\Core\App;
use Clover\Nano\Exception\DBQueryError;

/**
 * Class Settings
 * @package Module
 */
class Settings
{
    /**
     * @var App
     */
    private $app;

    /**
     * @var Module
     */
    private $module;

    /**
     * @var array
     */
    private $settings = [];

    /**
     * Setting constructor.
     * @param App $app
     * @param Module $module
     * @param $merchantId
     * @throws DBQueryError
     */
    public function __construct(App $app, Module $module, $merchantId = 0)
    {
        $this->app = $app;
        $this->module = $module;
        $this->settings = (array)$module->Model->Settings->load($merchantId);

    }

    /**
     * @param $name
     * @param null $default
     * @return array|mixed|null
     */
    public function get($name, $default = null)
    {
        if ($name === '*')
            return $this->settings;
        elseif (isset($this->settings[$name]))
            return $this->settings[$name];
        else
            return $default;
    }

    /**
     * @param string $name
     * @param string|array $data
     * @return bool|int|mysqli_result
     * @throws DBQueryError
     */
    public function updateSetting($name, $data)
    {
        return $this->module->Model->Settings->updateSetting($name, $data);
    }
}