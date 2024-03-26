<?php

namespace App\IO;

interface IOInterface
{
    public function write(string $data): void;

    public function info(string $message): void;
}
