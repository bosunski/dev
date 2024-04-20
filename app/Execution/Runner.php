<?php

namespace App\Execution;

use App\Config\Config;
use App\Contracts\EnvResolver;
use App\Exceptions\UserException;
use App\IO\IOInterface;
use App\Plugin\Contracts\Step;
use App\Process\ProcProcess;
use App\Repository\Repository;
use Exception;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\InvokedProcess;
use Illuminate\Process\PendingProcess;
use Illuminate\Process\ProcessPoolResults;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command as Cmd;
use Symfony\Component\Process\Process as SymfonyProcess;

class Runner
{
    protected ?EnvResolver $envResolver = null;

    public function __construct(
        private readonly Config $config,
        private readonly IOInterface $io,
        protected readonly Repository $stepRepository
    ) {
    }

    public function setEnvResolver(EnvResolver $envResolver): void
    {
        $this->envResolver = $envResolver;
    }

    /**
     * @param Step|Step[] $steps
     *
     * @throws Exception
     */
    public function execute(array|Step $steps = [], bool $throw = false): int
    {
        try {
            if (! is_array($steps)) {
                $steps = [$steps];
            }

            foreach ($steps as $step) {
                $id = $step->id();
                if (isset($this->stepRepository->steps[$id])) {
                    continue;
                }

                $this->stepRepository->steps[$id] = $step;
                $this->executeStep($step);
            }

            return Cmd::SUCCESS;
        } catch (UserException $e) {
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

        $name = $step->name();
        if ($name) {
            $this->io->writeln($name);
        }

        $done = $step->run($this);

        if (! $done) {
            throw new UserException("Failed to run step: {$step->name()}");
        }
    }

    /**
     * @param string[]|string $command
     * @param null|string $path
     * @param array<string, string> $env
     * @return bool
     * @throws InvalidArgumentException
     */
    public function exec(string|array $command, ?string $path = null, array $env = []): bool
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

    /**
     * @param string[]|string $command
     * @param null|string $path
     * @param array<string, string> $env
     * @return InvokedProcess
     * @throws InvalidArgumentException
     */
    public function spawn(string|array $command, ?string $path = null, array $env = []): InvokedProcess
    {
        return $this->process($command, $path, $env)
            ->tty()
            ->start(output: $this->handleOutput(...));
    }

    /**
     * @param array<string, string|null> $env
     * @return array<string, string|null>
     * @throws InvalidArgumentException
     */
    private function environment(array $env = []): array
    {
        /**
         * ToDo: Review this precedence order and make sure it's correct.
         */
        return collect($env)
            ->merge(getenv())
            ->merge($this->envResolver?->envs() ?? [])
            ->merge($this->config->envs())->all();
    }

    /**
     * @param string[]|string $command
     * @param null|string $path
     * @param array<string, string> $env
     * @return PendingProcess
     * @throws InvalidArgumentException
     */
    public function process(array|string $command, ?string $path = null, array $env = []): PendingProcess
    {
        $shOptions = 'ec';
        if ($this->config->isDebug()) {
            $shOptions .= 'v';
        }

        $command = is_string($command)
            ? ['/opt/homebrew/bin/shadowenv', 'exec', '--', '/bin/sh', "-$shOptions", $command]
            : ['/opt/homebrew/bin/shadowenv', 'exec', '--', ...$command];

        return Process::forever()
            ->path($path ?? $this->config->cwd())
            ->command($command)
            ->env($this->environment($env));
    }

    /**
     * @param string[]|string $command
     * @param null|string $path
     * @param array<string, string|null> $env
     * @return SymfonyProcess
     * @throws InvalidArgumentException
     */
    public function symfonyProcess(array|string $command, ?string $path = null, array $env = []): SymfonyProcess
    {
        $shOptions = 'ec';
        if ($this->config->isDebug()) {
            $shOptions .= 'v';
        }

        $command = is_string($command)
            ? ['/opt/homebrew/bin/shadowenv', 'exec', '--', '/bin/sh', "-$shOptions", $command]
            : ['/opt/homebrew/bin/shadowenv', 'exec', '--', ...$command];

        return new SymfonyProcess($command, $path ?? $this->config->cwd(), $this->environment($env), timeout: 0);
    }

    /**
     * @param string[]|string $command
     * @param null|string $path
     * @param array<string, string|null> $env
     * @return ProcProcess
     * @throws InvalidArgumentException
     */
    public function procProcess(array|string $command, ?string $path = null, array $env = []): ProcProcess
    {
        $shOptions = 'ec';
        if ($this->config->isDebug()) {
            $shOptions .= 'v';
        }

        $command = is_string($command)
            ? ['/opt/homebrew/bin/shadowenv', 'exec', '--', '/bin/sh', "-$shOptions", $command]
            : ['/opt/homebrew/bin/shadowenv', 'exec', '--', ...$command];

        return new ProcProcess($command, $path, $this->environment($env));
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
