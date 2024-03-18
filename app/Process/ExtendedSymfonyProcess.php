<?php

namespace App\Process;

class ExtendedSymfonyProcess extends \Symfony\Component\Process\Process
{
    public function __construct(array $command, ?string $cwd = null, array $env = null, $input = null, ?float $timeout = 60, array $options = [])
    {
        parent::__construct($command, $cwd, $env, $input, $timeout, $options);
        $this->setPty(true);
    }

    /**
     * Returns whether PTY is supported on the current operating system.
     */
    public static function isPtySupported(): bool
    {
        return true;
    }
}
