<?php

namespace App\Execution;

use App\Config\Config;
use App\Step\StepInterface;
use Exception;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Command\Command as Cmd;

class Runner
{
    /**
     * @param Config $config
     * @param Command $command
     */
    public function __construct(private readonly Config $config, private readonly Command $command)
    {
    }

    /**
     * @throws Exception
     */
    public function execute(): int
    {
        try {
            foreach ($this->config->up()->steps() as $step) {
                $this->command->task($step->name(), fn () => $this->executeStep($step));
            }

            return Cmd::SUCCESS;
        } catch (ProcessFailedException) {
            return Cmd::FAILURE;
        }
    }

    private function executeStep(StepInterface $step): bool
    {
        if ($step->done($this)) {
            return true;
        }

        return $step->run($this);
    }

    public function exec(string $command): bool
    {
        return Process::run($command)->successful();
    }

    public function spawn(string $command): bool
    {
        return Process::run($command, $this->handleOutput(...))->throw()->successful();
    }

    private function handleOutput(string $_, string $output): void
    {
        echo $_, $output;
    }

    public function io(): Command
    {
        return $this->command;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function path(?string $morePath): string
    {
        return $this->config->path($morePath);
    }
}
