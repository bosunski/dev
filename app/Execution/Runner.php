<?php

namespace App\Execution;

use App\Config\Config;
use App\Contracts\EnvResolver;
use App\Exceptions\UserException;
use App\IO\IOInterface;
use App\Plugin\Contracts\Step;
use Exception;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\InvokedProcess;
use Illuminate\Process\PendingProcess;
use Illuminate\Process\ProcessPoolResults;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Command\Command as Cmd;
use Symfony\Component\Process\Process as SymfonyProcess;

class Runner
{
    protected ?EnvResolver $envResolver = null;

    public function __construct(
        private readonly Config $config,
        private readonly IOInterface $io
    ) {
    }

    public function setEnvResolver(EnvResolver $envResolver): void
    {
        $this->envResolver = $envResolver;
    }

    /**
     * @param  Step[]  $steps
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
            return $this->process($command, $path, $env)
                ->tty()
                ->run(output: $this->handleOutput(...))
                ->throw()
                ->successful();
        } catch (ProcessFailedException) {
            return false;
        }
    }

    public function spawn(string $command, ?string $path = null, array $env = []): InvokedProcess
    {
        return $this->process($command, $path, $env)
            ->tty()
            ->start(output: $this->handleOutput(...));
    }

    private function environment(array $env = []): array
    {
        return $this->config
            ->envs()
            ->merge(getenv())
            ->merge($env)
            ->merge($this->envResolver?->envs() ?? [])
            ->all();
    }

    public function process(array|string $command, ?string $path = null, array $env = []): PendingProcess
    {
        $command = is_string($command)
            ? ['/opt/homebrew/bin/shadowenv', 'exec', '--', '/bin/sh', '-c', $command]
            : ['/opt/homebrew/bin/shadowenv', 'exec', '--', ...$command];

        return Process::forever()
            ->path($path ?? $this->config->cwd())
            ->command($command)
            ->env($this->environment($env));
    }

    public function symfonyProcess(array|string $command, ?string $path = null, array $env = []): SymfonyProcess
    {
        $command = is_string($command)
            ? ['/opt/homebrew/bin/shadowenv', 'exec', '--', '/bin/sh', '-c', $command]
            : ['/opt/homebrew/bin/shadowenv', 'exec', '--', ...$command];

        return new SymfonyProcess($command, $path ?? $this->config->cwd(), $this->environment($env), timeout: 0);
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
