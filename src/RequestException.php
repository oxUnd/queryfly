<?php namespace Epsilon\Queryfly;

use Exception;

/**
 * if Request exception, throw RequestException
 *
 *
 */
class RequestException extends Exception 
{
    public function __construct($url, $message = '', $code = 0)
    {
        parent::__construct("url[{$url}] error[{$message}]", $code);
    }
}