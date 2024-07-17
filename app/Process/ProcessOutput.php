<?php

namespace App\Process;

use Hyperf\Engine\Channel;
use Hyperf\Engine\Coroutine;

class ProcessOutput
{
    protected int $maxNameLength = 0;

    /**
     * @var Channel<string>
     */
    protected readonly Channel $channel;

    public function __construct()
    {
        $this->channel = new Channel(512);

        $this->start();
    }

    public function start(): void
    {
        Coroutine::create(function (): void {
            while (true) {
                $output = $this->channel->pop();
                if ($output === false) {
                    break;
                }

                fwrite(STDOUT, $output);
            }
        });
    }

    public function addProcess(Process $process): void
    {
        $this->maxNameLength = max($this->maxNameLength, strlen($process->name));
    }

    public function writeOutput(Process $process, string $output): void
    {
        $color = sprintf("\033[1;38;5;%s", $process->color);
        $lines = $this->outputToLines($output);
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $spaces = '';
            foreach (range(strlen($process->name), $this->maxNameLength) as $i) {
                $spaces .= ' ';
            }

            $this->channel->push("\033[{$color}m" . $process->name . $spaces . "\033[0m| " . $line);
        }
    }

    public function writeError(Process $process, string $output): void
    {
        $this->writeOutput($process, sprintf("\033[0;31m%s\033[0m\n", $output));
    }

    /**
     * @param string $string
     * @return string[]
     */
    protected function outputToLines(string $string): array
    {
        $lines = preg_split("/(?<=\r\n|\n|\r)/", $string);

        return $lines === false ? [] : $lines;
    }

    public function close(): void
    {
        $this->channel->close();
    }
}
