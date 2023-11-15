<?php

namespace App\Step;

use App\Execution\Runner;

interface StepInterface
{
    public const PRIORITY_HIGH = 1;

    public const PRIORITY_NORMAL = 2;

    public const PRIORITY_LOW = 3;

    public function name(): ?string;

    public function run(Runner $runner): bool;

    public function done(Runner $runner): bool;

    public function id(): string;
}
