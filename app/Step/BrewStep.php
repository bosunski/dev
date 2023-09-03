<?php

namespace App\Step;

use App\Execution\Runner;

class BrewStep implements StepInterface
{
    public function __construct(private readonly array $packages)
    {
    }

    public function name(): string
    {
        $packages = implode(', ', $this->packages);
        return "Install brew packages: $packages";
    }

    public function command(): ?string
    {
        return "brew install " . implode(' ', $this->packages);
    }

    public function checkCommand(): ?string
    {
        return $this->command();
    }

    public function run(Runner $runner): bool
    {
        return $runner->exec($this->command());
    }

    public function done(Runner $runner): bool
    {
        return $runner->exec($this->checkCommand());
    }
}
