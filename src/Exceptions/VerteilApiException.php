<?php

namespace Santosdave\VerteilWrapper\Exceptions;

use Exception;

class VerteilApiException extends Exception
{
    protected $errorResponse;

    public function __construct($message = "", $code = 0, Exception $previous = null, $errorResponse = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorResponse = $errorResponse;
    }

    public function getErrorResponse()
    {
        return $this->errorResponse;
    }

    public function getErrorMessage(): string
    {
        return $this->message;
    }
}
