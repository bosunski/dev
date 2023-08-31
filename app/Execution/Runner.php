<?php

namespace App\Execution;

use App\Config\Config;
use App\Exceptions\UserException;
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
     * @param StepInterface[] $steps
     * @throws Exception
     */
    public function execute(array $steps = []): int
    {
        try {
            foreach ($steps as $step) {
                $this->command->getOutput()->writeln("✨ {$step->name()}");
                $this->executeStep($step);
            }

            return Cmd::SUCCESS;
        } catch (ProcessFailedException | UserException) {
            return Cmd::FAILURE;
        }
    }

    /**
     * @throws UserException
     */
    private function executeStep(StepInterface $step): bool
    {
        if ($step->done($this)) {
            return true;
        }

        $done = $step->run($this);

        if (! $done) {
            throw new UserException("Failed to run step: {$step->name()}");
        }

        return true;
    }

    public function exec(string $command): bool
    {
        return Process::forever()->tty()->run($command, $this->handleOutput(...))->throw()->successful();
    }

    public function spawn(string $command): bool
    {
        return Process::forever()->tty()->run($command, $this->handleOutput(...))->throw()->successful();
    }

    private function handleOutput(string $_, string $output): void
    {
        $this->io()->getOutput()->write($output);
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
