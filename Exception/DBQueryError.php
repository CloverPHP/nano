<?php


namespace Clover\Nano\Exception;

/**
 * Class DBQueryError
 * @package Clover\Nano\Exception
 */
class DBQueryError extends UnexpectedError
{
    protected $error = 'dbquery_error';
}