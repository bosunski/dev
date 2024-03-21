<?php

namespace App\Process;

use Illuminate\Console\Command;

class ProcessOutput
{
    protected int $maxNameLength = 0;

    public function __construct(protected Command $command)
    {
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

            fwrite(STDOUT, "\033[{$color}m");

            $spaces = '';
            foreach (range(strlen($process->name), $this->maxNameLength) as $i) {
                $spaces .= ' ';
            }

            fwrite(STDOUT, $process->name . $spaces . "\033[0m| " . $line);
        }
    }

    public function writeError(Process $process, string $output): void
    {
        $this->writeOutput($process, sprintf("\033[0;31m%s\033[0m\n", $output));
    }

    protected function outputToLines(string $string): array
    {
        return preg_split("/(?<=\r\n|\n|\r)/", $string);
    }
}
