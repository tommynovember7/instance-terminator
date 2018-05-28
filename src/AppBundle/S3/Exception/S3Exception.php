<?php

namespace AppBundle\S3\Exception;

use \Exception;

/**
 * Class S3Exception
 *
 * @package AppBundle\S3\Exception
 */
class S3Exception extends Exception
{
    /**
     * S3Exception constructor.
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
