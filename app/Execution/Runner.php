<?php

namespace App\Execution;

use App\Config\Config;
use App\Exceptions\UserException;
use App\IO\IOInterface;
use App\Plugin\Contracts\Step;
use Exception;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\InvokedProcess;
use Illuminate\Process\ProcessPoolResults;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Command\Command as Cmd;

class Runner
{
    public function __construct(
        private readonly Config $config,
        private readonly IOInterface $io
    ) {
    }

    /**
     * @param  StepInterface[]  $steps
     *
     * @throws Exception
     */
    public function execute(array $steps = [], bool $throw = false): int
    {
        try {
            foreach ($steps as $step) {
                $name = $step->name();
                if ($name) {
                    $this->io->info($step->name());
                }

                $this->executeStep($step);
            }

            return Cmd::SUCCESS;
        } catch (ProcessFailedException|UserException $e) {
            if ($throw) {
                throw $e;
            }

            return Cmd::FAILURE;
        }
    }

    /**
     * @throws UserException
     */
    private function executeStep(Step $step): void
    {
        if ($step->done($this)) {
            return;
        }

        $done = $step->run($this);

        if (! $done) {
            throw new UserException("Failed to run step: {$step->name()}");
        }
    }

    public function exec(string $command, ?string $path = null, array $env = []): bool
    {
        try {
            return Process::forever()
                ->env($this->environment($env))
                ->tty()
                ->path($path ?? $this->config->cwd())
                ->run(['shadowenv', 'exec', '--', 'sh', '-c', $command], $this->handleOutput(...))
                ->throw()
                ->successful();
        } catch (ProcessFailedException) {
            return false;
        }
    }

    public function spawn(string $command, ?string $path = null, array $env = []): InvokedProcess
    {
        return Process::forever()
            ->env($this->environment($env))
            ->tty()
            ->path($path ?? $this->config->cwd())
            ->start(['shadowenv', 'exec', '--', 'sh', '-c', $command], $this->handleOutput(...));
    }

    private function environment(array $env = []): array
    {
        return $this->config
            ->environment
            ->merge(getenv())
            ->merge($env)
            ->merge([
                'SOURCE_ROOT'  => Config::sourcePath(),
                'SERVICE_ROOT' => $this->config->servicePath(),
                'DEV_PATH'     => $this->config->devPath(),
            ])->all();
    }

    public function pool(callable $callback): ProcessPoolResults
    {
        return Process::pool($callback)->start($this->handleOutput(...))->wait();
    }

    private function handleOutput(string $_, string $output, ?string $key = null): void
    {
        $this->io()->write($output);
    }

    public function io(): IOInterface
    {
        return $this->io;
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
