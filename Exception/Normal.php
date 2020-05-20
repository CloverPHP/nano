<?php

namespace Clover\Nano\Exception;

/**
 * Class Normal
 * @package Clover\Nano\Exception
 */
abstract class Normal extends Base
{
    protected $status = 'ok';
    protected $error = '';

}