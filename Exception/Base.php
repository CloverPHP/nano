<?php

namespace Clover\Nano\Exception;

/**
 * Class Base
 * @package Clover\Nano\Exception
 */
abstract class Base extends \Exception
{
    protected $status = 'error';
    protected $error = '';
    protected $desc = '';
    protected $extra = [];
    protected $data = [];

    public function __construct($message = '', $code = '', $extra = [])
    {
        parent::__construct($message);
        $this->desc = $message . "@" . $this->getFile() . ":" . $this->getLine();
        $this->data = [
            'status' => 'error',
            'error' => $this->error,
            'desc' => $this->desc,
            'profiler' => [],
            'systrace' => $this->getTraceAsString()
        ];
        $this->data['code'] = $code ? $code : '';
        $this->extra = array_replace($this->extra, $extra);
        $this->data = array_replace($this->extra, $this->data);
    }

    public function addExtra($k, $v)
    {
        $this->data[$k] = $v;
    }

    public function fetch()
    {
        return $this->data;
    }
}