<?php

namespace App\Exceptions;

class UnknownShellException extends UserException
{
    public function __construct()
    {
        parent::__construct('Unable to determine the current shell. Make sure you are using one of the supported shells: bash, zsh, fish.');
    }
}
