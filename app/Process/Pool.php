<?php

namespace App\Process;

use Amp\Process\Process;
use function Amp\async;
use function Amp\ByteStream\getStderr;
use function Amp\ByteStream\getStdout;
use function Amp\ByteStream\pipe;
use function Amp\Future\await;

class Pool
{
    /**
     * @var array<string, Process>
     */
    protected array $processes = [];

    public function add(string $key, Process $process): void
    {
        $this->processes[$key] = $process;

        async(fn () => pipe($process->getStdout(), getStdout()));
        async(fn () => pipe($process->getStderr(), getStderr()));
    }

    public function join(): void
    {
        await(collect($this->processes)->map(fn (Process $process) => async(fn () => $this->wait($process))));
    }

    public function wait(Process $process): void
    {
        $process->join();
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->kill();
            }
        }
    }
}
