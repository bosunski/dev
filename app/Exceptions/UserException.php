<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class UserException extends Exception
{
    public function __construct(string $title, public readonly string $body = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($title, $code, $previous);
    }
}
