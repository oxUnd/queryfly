<?php namespace Epsilon\Queryfly\Query;

use Throwable;

/**
 * if statement or clauses not support, throw QueryNotSupportException.
 *
 */
class NotSupportException extends Exception
{
    protected $messageFormat = 'Not support: %s';

    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        $this->message = sprintf($this->messageFormat, $message);

        parent::__construct($message, $code, $previous);
    }
}