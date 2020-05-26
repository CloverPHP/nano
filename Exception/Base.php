<?php

namespace Exception;


/**
 * Class Base
 * @package Exception
 */
class Base extends \Clover\Nano\Exception\Base
{
    /**
     * Base constructor.
     * @param string $desc
     * @param string $code
     * @param array $extra
     */
    final public function __construct($desc, $code = '', $extra = [])
    {
        parent::__construct($desc, $code, $extra);
    }
}