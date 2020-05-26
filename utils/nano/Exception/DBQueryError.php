<?php


namespace Clover\engins\Exception;

/**
 * Class DBQueryError
 * @package Clover\Nano\Exception
 */
class DBQueryError extends UnexpectedError
{
    protected $error = 'dbquery_error';
}