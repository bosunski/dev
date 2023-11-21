<?php

namespace App\Step;

use App\Execution\Runner;
use Illuminate\Support\Str;

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
        $command = $this->config['meet'] ?? $this->config['run'] ?? null;
        if ($command && is_array($command)) {
            return $command[0];
        }

        return $command;
    }

    public function checkCommand(): ?string
    {
        $command = $this->config['met?'] ?? null;
        if ($command && is_array($command)) {
            return $command[0];
        }

        return $command;
    }

    private function hasCheckCommand(): bool
    {
        return $this->checkCommand() !== null;
    }

    public function run(Runner $runner): bool
    {
        if ($this->hasCheckCommand() && ! $this->command()) {
            return false;
        }

        return $runner->exec($this->command(), $runner->config()->cwd());
    }

    public function done(Runner $runner): bool
    {
        if (! $this->hasCheckCommand()) {
            return false;
        }

        return $runner->exec($this->checkCommand(), $runner->config()->cwd());
    }

    public function id(): string
    {
        return Str::random(10);
    }
}
