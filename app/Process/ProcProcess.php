<?php

namespace App\Process;

use Closure;
use Exception;
use LogicException;
use RuntimeException;

class ProcProcess
{
    public const CHUNK_SIZE = 16384;

    private int $exitcode = -1;

    /**
     * @var resource | null
     */
    protected $process = null;

    /**
     * @var array{command: string, pid: int, running: bool, signaled: bool, stopped: bool, exitcode: int, termsig: int, stopsig: int}
     */
    protected array $info = [
        'command'  => '',
        'pid'      => 0,
        'running'  => false,
        'signaled' => false,
        'stopped'  => false,
        'exitcode' => -1,
        'termsig'  => 0,
        'stopsig'  => 0,
    ];

    /**
     * @var resource[]
     */
    protected array $pipes = [];

    protected int $cachedExitCode = -1;

    protected ?Closure $output = null;

    /**
     * @param string|string[] $command
     * @param string|null $cwd
     * @param array<string, string|null> $envs
     *
     * @return void
     */
    public function __construct(public readonly string|array $command, public readonly ?string $cwd = null, public readonly array $envs = [])
    {
    }

    public function setPty(bool $pty): ProcProcess
    {
        return $this;
    }

    public function start(callable $output): void
    {
        $this->output = Closure::fromCallable($output);
        // $descriptorspec = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        $descriptorspec = [['pipe', 'r'], ['pty'], ['pty']];
        // $descriptorspec = [['pty'], ['pty'], ['pty']];
        $envPairs = [];
        $envs = $this->envs + $this->getDefaultEnv();
        foreach ($envs as $k => $v) {
            if ($v !== false && \in_array($k, ['argc', 'argv', 'ARGC', 'ARGV'], true) === false) {
                $envPairs[] = $k . '=' . $v;
            }
        }
        $process = proc_open($this->command, $descriptorspec, $pipes, $this->cwd, $envPairs);
        if (! $process) {
            throw new Exception('Unable to start process');
        }

        $this->process = $process;
        $this->pipes = $pipes;
        $this->updateStatus();
    }

    public function wait(): int
    {
        if (! $this->process) {
            throw new RuntimeException('You need to start the process first');
        }

        while ($this->isRunning()) {
            $w = $e = [];
            $pipes = $this->pipes;
            $s = stream_select($pipes, $w, $e, 1, 0);

            if ($s === 0) {
                continue;
            }

            foreach ($pipes as $pipe) {
                /**
                 * We don't want to read from the STDIN, we only care
                 * about the outputs for now.
                 */
                if ($pipe === $this->pipes[0]) {
                    continue;
                }

                $out = @fread($pipe, self::CHUNK_SIZE);
                if ($out === '' || $out === false) {
                    break;
                }

                if (isset($out[0])) {
                    $this->output($out);
                }
            }
        }

        $this->close();

        return $this->exitcode;
    }

    private function output(string $chunk): void
    {
        if ($this->output) {
            call_user_func($this->output, '', $chunk);
        }
    }

    public function isRunning(): bool
    {
        if (! is_resource($this->process)) {
            return false;
        }

        $this->updateStatus();

        return $this->info['running'] === true;
    }

    public function signal(int $signal): bool
    {
        if (! $this->process || empty($this->info) || ! $this->isRunning()) {
            throw new LogicException('Cannot send signal on a non running process.');
        }

        return posix_kill($this->info['pid'], $signal);
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

        if (! $running) {
            $this->close();
        }
    }

    /**
     * Closes process resource, closes file handles, sets the exitcode.
     *
     * @return int The exitcode
     */
    public function close(): int
    {
        // $this->closePipes();
        if (is_resource($this->process)) {
            proc_close($this->process);
        }

        $this->exitcode = $this->info['exitcode'];
        // $this->status = self::STATUS_TERMINATED;

        if ($this->exitcode === -1) {
            if ($this->info['signaled'] && $this->info['termsig'] > 0) {
                // if process has been signaled, no exitcode but a valid termsig, apply Unix convention
                $this->exitcode = 128 + $this->info['termsig'];
            }
        }

        // Free memory from self-reference callback created by buildCallback
        // Doing so in other contexts like __destruct or by garbage collector is ineffective
        // Now pipes are closed, so the callback is no longer necessary
        $this->output = null;

        return $this->exitcode;
    }

    protected function closePipes(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $this->pipes = [];
    }

    /**
     * @return array<string, string>
     */
    private function getDefaultEnv(): array
    {
        return $_ENV + getenv();
    }
}
