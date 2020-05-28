<?php


namespace Clover\Nano;

use ReflectionClass;
use ReflectionException;
use Clover\Nano\Core\App;
use Clover\Nano\Exception\InvalidParams;

/**
 * Class Controller
 */
class Controller
{

    /**
     * @var App
     */
    protected $app;

    /**
     * Controller constructor.
     * @param App $app
     */
    final public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * @param App $app
     * @throws ReflectionException
     */
    public function __invoke(App $app)
    {
        $name = $this->prepareRequest();
        $name = "App\\{$name}";
        $class = new ReflectionClass($name);
        $app->event->emit('before_action', [$app]);
        $action = $class->newInstanceArgs([$app]);
        $action->__invoke($app);
        $app->event->emit('after_action', [$app]);
    }


    /**
     * 准备好nano特有的参数
     * @return string
     */
    final private function prepareRequest()
    {
        $pathInfo = $this->app->getServerParam('path_info');
        $pathInfo = trim($pathInfo, "\t\n\r \v/ ");
        if (!$pathInfo) $pathInfo = 'Index/Index';

        $parts = array_map(function ($v) use ($pathInfo) {
            if (preg_match('/^[a-z_][\w]*$/i', $v))
                return ucfirst($v);
            else
                throw new InvalidParams("error path :{$pathInfo}.", 'invalid_pathinfo');

        }, explode("/", $pathInfo));

        $className = implode("\\", $parts);
        $this->app->request->setUri(str_replace('\\', '/', $className));
        $this->app->request->setHeader([
            'service' => array_shift($parts),
            'action' => !empty($parts) ? implode("\\", $parts) : 'Index',
            'username' => $this->app->getServerParam('username', ''),
            'password' => $this->app->getServerParam('password', ''),
        ]);
        return $className;
    }
}