<?php

namespace Model;

use Clover\Nano\Core\App;
use Clover\Nano\Core\Model;
use Clover\Nano\Exception\InternalError;
use ReflectionClass;
use ReflectionException;

/**
 * Class Model
 * @package Model
 * @property AccessLog $AccessLog
 * @property Settings $Settings
 */
final class Loader extends Model
{
    /**
     * @var App
     */
    private $app;

    /**
     * @var array
     */
    private $components = [];

    /** @noinspection PhpMissingParentConstructorInspection */

    /**
     * Loader constructor.
     * @param App $app
     */
    final public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * @param $modelName
     * @param $params
     * @return mixed|object
     * @throws InternalError
     */
    private function getModel($modelName, $params = null)
    {
        $className = "\\Model\\" . $modelName;

        if (isset($this->components[$className])) {
            return $this->components[$className];
        } else {
            if (class_exists($className)) {
                $parameters = [$this];
                if (!empty($params) && !is_array($params)) {
                    $parameters[] = &$params;
                } elseif (!empty($params) && is_array($params)) {
                    foreach ($params as &$item)
                        $parameters[] = &$item;
                }
                try {
                    $refClass = new ReflectionClass($className);
                    $object = $refClass->newInstanceArgs($parameters);
                    if ($object instanceof Model) {
                        $this->components[$className] = $object;
                        return $object;
                    } else {
                        throw new InternalError("Model Invalid:{$className}");
                    }
                } catch (ReflectionException $ex) {
                    throw new InternalError($ex->getMessage());
                }
            } else {
                throw new InternalError("Model Not Found:{$className}");
            }
        }
    }

    /**
     * @param $name
     * @return mixed
     * @throws InternalError
     */
    final public function __get($name)
    {
        if (!isset($this->components[$name]))
            $this->components[$name] = $this->getModel($name);
        return $this->components[$name];
    }
}