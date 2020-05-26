<?php


namespace Clover\Nano\Exception;

/**
 * Class InternalError
 * @package Clover\Nano\Exception
 */
class InternalError extends UnexpectedError
{
    protected $error = 'unexpected_error';
}