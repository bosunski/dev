<?php

namespace App\Step;

use App\Execution\Runner;
use App\Repository\Repository;

class DeferredStep implements StepInterface
{
    public function __construct(private readonly Repository $repository, protected readonly StepInterface $step)
    {
    }

    public function name(): string
    {
        return "{$this->step->name()} (deferred)";
    }

    public function run(Runner $runner): bool
    {
        $this->repository->addDeferred($this);

        return true;
    }

    public function id(): string
    {
        return $this->step->id();
    }

    public function done(Runner $runner): bool
    {
        return false;
    }

    public function step(): StepInterface
    {
        return $this->step;
    }
}
