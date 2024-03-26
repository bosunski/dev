<?php

namespace App\Process;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process as SymfonyProcess;

use Throwable;

class Process
{
    protected Channel $signalChannel;

    public function __construct(
        public readonly string $name,
        public readonly string $color,
        protected readonly ProcessOutput $output,
        protected readonly SymfonyProcess $process
    ) {
        $this->signalChannel = new Channel();
    }

    public function start(): void
    {
        try {
            $this->process->start(function (string $type, string $buffer): void {
                $this->writeOutput($buffer);
            });

            Coroutine::create(function (): void {
                while($signal = $this->signalChannel->pop()) {
                    $this->signal($signal);
                }
            });

            $this->writeExitMessage($this->process->wait());
        } catch (ProcessSignaledException $e) {
            $this->writeExitMessage($e->getProcess()->getExitCode());
        } catch (Throwable $e) {
            $this->writeError("\033[1mError: {$e->getMessage()}\033[0m");
        } finally {
            $this->signalChannel->close();
        }
    }

    protected function writeExitMessage(int $exitCode): void
    {
        if ($this->isInterrupted($exitCode)) {
            $this->writeError('Signal: Interrupted');

            return;
        }

        if ($exitCode !== 0) {
            $this->writeError("\033[1mExit Status: $exitCode\033[0m");

            return;
        }

        $this->writeOutput("\033[1mExit Status: $exitCode\033[0m\n");
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
            if (! $this->process->isRunning()) {
                return;
            }

            $this->process->signal($signal);
        } catch (Throwable $e) {
            $this->writeError("(DEV) Sending Signal Failed: {$e->getMessage()}");
        }
    }

    public function doSignal(int $signal): void
    {
        $this->signalChannel->push($signal);
    }

    public function interrupt(): void
    {
        if (! $this->process?->isRunning()) {
            return;
        }

        $this->writeOutput("\033[1mInterrupting...\033[0m\n");
        $this->doSignal(SIGINT);
    }

    public function kill(): void
    {
        if (! $this->process?->isRunning()) {
            return;
        }

        $this->writeOutput("\033[1mKilling...\033[0m\n");
        $this->doSignal(SIGKILL);
    }
}
