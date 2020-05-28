<?php


namespace Clover\Nano\Exception;

/**
 * Class PermissionDenied
 * @package Clover\Nano\Exception
 */
class PermissionDenied extends Error
{
    protected $error = 'permission_denied';
}