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

    protected bool $usingShadowEnv = true;

    public function __construct(
        private readonly Config $config,
        private readonly IOInterface $io,
        protected readonly Repository $stepRepository
    ) {
    }

    public function withoutShadowEnv(): static
    {
        $new = clone $this;
        $new->usingShadowEnv = false;

        return $new;
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
    public function execute(array|Step $steps = [], bool $throw = false, bool $force = false): int
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
                $this->executeStep($step, $force);
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
    private function executeStep(Step $step, bool $force = false): void
    {
        if (! $force && $step->done($this)) {
            return;
        }

        $name = $step->name();
        if ($name) {
            $this->io->writeln($name);
        }

        if (! $step->run($this)) {
            throw new UserException("Failed to run step: $name");
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
            return $this->process($this->createShadowEnvCommand($command), $path, $env)
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
        return $this->process($this->createShadowEnvCommand($command), $path, $env)
            ->tty()
            ->start(output: $this->handleOutput(...));
    }

    /**
     * @param array<string, string> $env
     * @return array<string, string|null>
     * @throws InvalidArgumentException
     */
    private function environment(array $env = []): array
    {
        /**
         * ToDo: Review this precedence order and make sure it's correct.
         */
        return $this->config->envs()
            ->merge(getenv())
            ->merge($this->envResolver?->envs() ?? [])
            ->merge($env)->all();
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
        return Process::forever()
            ->path($path ?? $this->config->cwd())
            ->command($command)
            ->env($this->environment($env));
    }

    /**
     * @param string|string[] $command
     * @return string[]
     */
    protected function createShadowEnvCommand(string|array $command): array
    {
        $options = 'ec';
        if ($this->config->isDebug()) {
            $options .= 'v';
        }

        $shell = getenv('SHELL');
        if (! $shell) {
            $shell = '/bin/sh';
        }

        $commandPrefix = $this->usingShadowEnv ? ['/opt/homebrew/bin/shadowenv', 'exec', '--'] : [];
        $command = is_string($command) ? $command : implode(' ', $command);

        return array_merge($commandPrefix, [$shell, "-$options", $command]);
    }

    /**
     * @param string[]|string $command
     * @param null|string $path
     * @param array<string, string> $env
     * @return SymfonyProcess
     * @throws InvalidArgumentException
     */
    public function symfonyProcess(array|string $command, ?string $path = null, array $env = []): SymfonyProcess
    {
        return new SymfonyProcess($this->createShadowEnvCommand($command), $path ?? $this->config->cwd(), $this->environment($env), timeout: 0);
    }

    /**
     * @param string[]|string $command
     * @param null|string $path
     * @param array<string, string> $env
     * @return ProcProcess
     * @throws InvalidArgumentException
     */
    public function procProcess(array|string $command, ?string $path = null, array $env = []): ProcProcess
    {
        return new ProcProcess($this->createShadowEnvCommand($command), $path, $this->environment($env));
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
