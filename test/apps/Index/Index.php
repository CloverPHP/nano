<?php

namespace App\Index;

use Clover\Nano\Core\App;

/**
 * Class Index
 */
class Index
{

    /**
     * @var App
     */
    private $app;

    /**
     * Index constructor.
     * @param App $app
     */
    final public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * @param App $app
     */
    final public function __invoke(App $app)
    {
    }
}