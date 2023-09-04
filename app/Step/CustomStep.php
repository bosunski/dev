<?php

namespace App\Step;

use App\Execution\Runner;

class CustomStep implements StepInterface
{
    public function __construct(private readonly array $config)
    {
    }

    public function name(): string
    {
        return $this->config['name'] ?? '';
    }

    public function command(): ?string
    {
        return $this->config['meet'] ?? null;
    }

    public function checkCommand(): ?string
    {
        return $this->config['met?'] ?? null;
    }

    public function run(Runner $runner): bool
    {
        if (! $this->command()) {
            return false;
        }

        return $runner->exec($this->command(), $runner->config()->cwd());
    }

    public function done(Runner $runner): bool
    {
        return $runner->exec($this->checkCommand(), $runner->config()->cwd());
    }
}
