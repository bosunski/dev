<?php

namespace App\Plugins\Spc\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Spc\Config\SpcConfig;

class SpcCacheStep implements Step
{
    public function __construct(protected readonly SpcConfig $config)
    {
    }

    public function id(): string
    {
        return 'spc-cache';
    }

    public function name(): string
    {
        return 'Lock Downloaded SPC Dependencies';
    }

    public function run(Runner $runner): bool
    {
        return true;
    }

    public function done(Runner $runner): bool
    {
        return false;
    }
}
