<?php

namespace AppBundle\Ec2\Exception;

use \Exception;

/**
 * Class Ec2Exception
 *
 * @package AppBundle\Ec2\Exception
 */
class Ec2Exception extends Exception
{
    /**
     * Ec2Exception constructor.
     *
     * @param string         $message
     * @param int            $code
     * @param Exception|null $previous
     */
    public function __construct($message, $code = 500, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return __CLASS__.": [{$this->code}]: {$this->message}\n";
    }
}
