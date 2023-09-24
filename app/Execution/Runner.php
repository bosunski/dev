<?php

namespace App\Execution;

use App\Config\Config;
use App\Exceptions\UserException;
use App\Step\StepInterface;
use Exception;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\InvokedProcess;
use Illuminate\Process\ProcessPoolResults;
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

    public function exec(string $command, string $path = null): bool
    {
        try {
            return Process::forever()
                ->env(['SOURCE_ROOT' => Config::sourcePath()])
                ->tty()
                ->path($path ?? getcwd())
                ->run($command, $this->handleOutput(...))
                ->throw()
                ->successful();
        } catch (ProcessFailedException) {
            return false;
        }
    }

    public function spawn(string $command, string $path = null): InvokedProcess
    {
        return Process::forever()
            ->env(['SOURCE_ROOT' => Config::sourcePath()])
            ->tty()
            ->path($path ?? getcwd())
            ->start($command, $this->handleOutput(...));
    }

    public function pool(callable $callback): ProcessPoolResults
    {
        return Process::pool($callback)->start($this->handleOutput(...))->wait();
    }

    private function handleOutput(string $_, string $output, string $key = null): void
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
