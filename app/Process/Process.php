<?php

namespace App\Process;

use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Process\PendingProcess;
use Throwable;

class Process
{
    protected ?InvokedProcess $invokedProcess = null;

    public function __construct(
        public readonly string $name,
        public readonly string $color,
        protected readonly ProcessOutput $output,
        protected readonly PendingProcess $pendingProcess
    ) {
    }

    public function start(): void
    {
        try {
            $this->invokedProcess = $this->pendingProcess->start(output: function (string $type, string $buffer): void {
                $this->writeOutput($buffer);
            });
            $exitCode = $this->invokedProcess->wait()->exitCode();

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
            $this->invokedProcess?->signal($signal);
        } catch (Throwable $e) {
            $this->writeError("(DEV) Signal Failed: {$e->getMessage()}");
        }
    }

    public function interrupt(): void
    {
        if (! $this->invokedProcess?->running()) {
            return;
        }

        $this->writeOutput("\033[1mInterrupting...\033[0m\n");
        $this->signal(SIGINT);
    }

    public function kill(): void
    {
        if (! $this->invokedProcess?->running()) {
            return;
        }

        $this->writeOutput("\033[1mKilling...\033[0m\n");
        $this->signal(SIGKILL);
    }
}
