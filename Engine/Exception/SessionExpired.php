<?php


namespace Clover\Nano\Exception;

/**
 * Class SessionExpired
 * @package Clover\Nano\Exception
 */
class SessionExpired extends Error
{
    protected $error = 'session_expired';
}