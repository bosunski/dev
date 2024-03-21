<?php

namespace App\Process;

use App\Step\ServeStep;
use Symfony\Component\Process\Process as SymfonyProcess;
use Throwable;

class Process
{
    protected SymfonyProcess $process;

    public function __construct(
        public readonly string $name,
        public readonly string|array $command,
        public readonly string $color,
        public readonly array $env = [],
        protected readonly ProcessOutput $output,
        protected readonly ServeStep $step,
    ) {
        $command = is_string($command)
            ? ['shadowenv', 'exec', '--', '/bin/sh', '-c', $command]
            : ['shadowenv', 'exec', '--', ...$command];

        $this->process = new SymfonyProcess($command, env: $env, timeout: 0);
        $this->process->setPty(true);
    }

    public function start(): void
    {
        try {
            $exitCode = $this->process->setPty(true)->run(function (string $type, string $buffer): void {
                $this->writeOutput($buffer);
            });

            if ($this->isInterrupted($exitCode)) {
                $this->writeError('Signal: Interrupted');

                return;
            }

            if ($exitCode !== 0) {
                $this->writeError("\033[1mExit Status: $exitCode\033[0m");

                return;
            }

            $this->writeOutput("\033[1mExit Status: $exitCode\033[0m\n");
        } catch (Throwable $e) {
            $this->writeError("\033[1mError: {$e->getMessage()}\033[0m");
        }
    }

    public function isInterrupted(int $exit): bool
    {
        return $exit === 130;
    }

    public function wait(): void
    {
        $this->process->wait();
    }

    public function writeOutput(string $output): void
    {
        $this->output->writeOutput($this, $output);
    }

    public function writeError(string $output): void
    {
        $this->output->writeError($this, $output);
    }

    public function signal(int $signal): void
    {
        try {
            $this->process->signal($signal);
        } catch (Throwable $e) {
            $this->writeError("(DEV) Signal Failed: {$e->getMessage()}");
        }
    }

    public function interrupt(): void
    {
        if (! $this->process->isRunning()) {
            return;
        }

        $this->writeOutput("\033[1mInterrupting...\033[0m\n");
        $this->signal(SIGINT);
    }

    public function kill(): void
    {
        if (! $this->process->isRunning()) {
            return;
        }

        $this->writeOutput("\033[1mKilling...\033[0m\n");
        $this->signal(SIGKILL);
    }
}
