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
use Illuminate\Process\InvokedProcess;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use Symfony\Component\Process\Process as SymfonyProcess;

class Runner
{
    protected ?EnvResolver $envResolver = null;

    protected bool $usingShadowEnv = true;

    /**
     * @var array{name: string, bin: string, profile: string}|null
     */
    private ?array $shell = null;

    public function __construct(
        public readonly Config $config,
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
    public function execute(array|Step $steps = [], bool $force = false): bool
    {
        if (! is_array($steps)) {
            $steps = [$steps];
        }

        foreach ($steps as $step) {
            $id = $step->id();
            if (isset($this->stepRepository->steps[$id])) {
                continue;
            }

            $this->stepRepository->steps[$id] = $step;
            if (! $this->executeStep($step, $force)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws UserException
     */
    private function executeStep(Step $step, bool $force = false): bool
    {
        if (! $force && $step->done($this)) {
            return true;
        }

        $name = $step->name();
        if ($name) {
            $this->io->writeln($name);
        }

        return $step->run($this);
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
        return $this->process($this->createShadowEnvCommand($command), $path, $env)
            ->tty()
            ->run(output: $this->handleOutput(...))
            ->successful();
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
        $this->checkShadowEnv();

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
     * @return array{bool, bool}
     */
    public function checkShadowEnv(bool $force = false): array
    {
        /**
         * We will cache the results of the checks to avoid running them multiple times.
         */
        static $hookInstalled = null;
        static $binaryInstalled = null;

        if (! $force && $hookInstalled !== null && $binaryInstalled !== null) {
            return [$hookInstalled, $binaryInstalled];
        }

        if (! $shell = $this->shell(null)) {
            return [false, false];
        }

        $result = $this->process([$shell['bin'], '-c', "(source {$shell['profile']} && command -v __shadowenv_hook) >/dev/null 2>&1"])->run();
        $hookInstalled = $binaryInstalled = $this->usingShadowEnv = $result->successful();

        /**
         * It's highly unlikely that the hook will be installed and the binary not be installed.
         * So, we will return true if the hook is installed to save extra checks.
         */
        if ($hookInstalled) {
            return [$hookInstalled, $binaryInstalled];
        }

        $binaryInstalled = $this->process(['command', '-v', 'shadowenv'])->run()->successful();

        return [false, $binaryInstalled];
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

    /**
     * Resolve the current shell, shell name, and profile file.
     *
     * @return array{name: string, bin: string, profile: string}
     * @throws UserException
     */
    public function shell(?string $default = '/bin/bash'): ?array
    {
        if ($this->shell) {
            return $this->shell;
        }

        $bin = getenv('SHELL') ?: $default;
        if (! $bin) {
            return null;
        }

        $name = basename($bin);
        $profile = $this->profile($name);

        return compact('name', 'bin', 'profile');
    }

    /**
     * Returns possible shell configs for a given shell
     *
     * @param string $shell
     * @return string
     * @throws UserException
     */
    private function profile(string $shell): string
    {
        $possibleProfile = match ($shell) {
            'bash'  => ['.bash_profile', '.bashrc', 'bash_profile', 'bashrc', '.profile'],
            'zsh'   => ['.zshrc'],
            'fish'  => ['config.fish'],
            default => throw new UserException("Unknown shell: $shell. Supported shells are: bash, zsh, fish."),
        };

        foreach ($possibleProfile as $profile) {
            if (is_file($realPath = $this->config->home($profile))) {
                return $realPath;
            }
        }

        throw new UserException("Unable to find the profile file for the shell: $shell. Supported shells are: bash, zsh, fish.");
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
