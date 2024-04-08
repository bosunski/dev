<?php

namespace App\Process;

use Exception;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class ProcProcess
{
    /**
     * @var resource | null
     */
    protected $process = null;

    /**
     * @var array{command: string, pid: int, running: bool, signaled: bool, stopped: bool, exitcode: int, termsig: int, stopsig: int}
     */
    protected ?array $info = null;

    protected int $cachedExitCode = -1;

    /**
     * @param string[] $command
     * @return void
     */
    public function __construct(protected readonly array $command)
    {
    }

    public function run(callable $output): int
    {
        $descriptorspec = [['pty'], ['pty'], ['pty']];

        $process = proc_open($this->command, $descriptorspec, $pipes);
        if (! $process) {
            throw new Exception('Unable to start process');
        }

        $this->process = $process;
        $this->updateStatus();

        while (! feof($pipes[2]) || ! feof($pipes[1])) {
            $out = stream_get_contents($pipes[2], 8192);
            if ($out) {
                $output($out);
            }

            $out = fread($pipes[1], 8192);
            if ($out) {
                $output($out);
            }

            $this->updateStatus();
        }

        return $this->wait();
    }

    protected function wait(): int
    {
        if (! $this->process) {
            return -1;
        }

        $chan = new Channel();
        Coroutine::create(function (Channel $chan, $process): void {
            while (true) {
                $status = proc_get_status($process);
                if (! $status['running']) {
                    $chan->push($status['exitcode']);

                    break;
                }
            }
        }, $chan, $this->process);

        $exitCode = $chan->pop();
        assert(is_int($exitCode), 'Exit code must be an integer');

        return $exitCode;
    }

    public function isRunning(): bool
    {
        if (! $this->process) {
            return false;
        }

        $status = proc_get_status($this->process);

        return $status['running'];
    }

    public function signal(int $signal): bool
    {
        if (! $this->process) {
            return false;
        }

        return proc_terminate($this->process, $signal);
    }

    /**
     * Updates the status of the process, reads pipes.
     */
    protected function updateStatus(): void
    {
        if (! $this->process) {
            return;
        }

        $this->info = proc_get_status($this->process);
        $running = $this->info['running'];

        // In PHP < 8.3, "proc_get_status" only returns the correct exit status on the first call.
        // Subsequent calls return -1 as the process is discarded. This workaround caches the first
        // retrieved exit status for consistent results in later calls, mimicking PHP 8.3 behavior.
        if (\PHP_VERSION_ID < 80300) {
            if (! isset($this->cachedExitCode) && ! $running && $this->info['exitcode'] !== -1) {
                $this->cachedExitCode = $this->info['exitcode'];
            }

            if (isset($this->cachedExitCode) && ! $running && $this->info['exitcode'] === -1) {
                $this->info['exitcode'] = $this->cachedExitCode;
            }
        }
    }
}
