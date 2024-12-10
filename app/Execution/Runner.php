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

    /**
     * Disable the injection of environment variables from plugins
     * which is done by calling the $this->envResolver. This is useful when you
     * want to run a command without loading plugin variables.
     *
     * @return static
     */
    public function withoutEnv(): static
    {
        $new = clone $this;
        $new->envResolver = null;

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
     * Runs a command through ShadowEnv, injecting environment variables
     * from plugins and the system. The command is run in a TTY mode.
     *
     * @param string[]|string $command
     * @param null|string $path
     * @param array<string, string> $env
     * @return bool true if the command was successful, false otherwise.
     * @throws InvalidArgumentException
     */
    public function exec(string|array $command, ?string $path = null, array $env = []): bool
    {
        return $this->spawn($command, $path, $env)->wait()->successful();
    }

    /**
     * Spawns a command through ShadowEnv, injecting environment variables
     * from plugins and the system. The command is run in a TTY mode.
     *
     * @param string[]|string $command
     * @param null|string $path
     * @param array<string, string> $env
     * @return InvokedProcess The invoked process.
     * @throws InvalidArgumentException
     */
    public function spawn(string|array $command, ?string $path = null, array $env = []): InvokedProcess
    {
        return $this->process($command, $path, $env)->tty()->start();
    }

    /**
     * Create a new process instance. By default, the process instance will
     * use the current working directory, inject environment variables and use
     * ShadowEnv if it's available.
     *
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
            ->command($this->createShadowEnvCommand($command))
            ->env($this->environment($env));
    }

    /**
     * @param array<string, string> $env
     * @return array<string, string|null>
     * @throws InvalidArgumentException
     */
    private function environment(array $env = []): array
    {
        return $this->config->envs()
            ->merge(getenv())
            ->merge($this->envResolver?->envs() ?? [])
            ->merge($env)->all();
    }

    /**
     * @param string|string[] $command
     * @return string|string[]
     */
    protected function createShadowEnvCommand(string|array $command): array|string
    {
        /**
         * If running through ShadowEnv is disabled, we will return the command as is.
         */
        if (! $this->usingShadowEnv) {
            return $command;
        }

        $this->checkShadowEnv();

        $options = 'ec';
        if ($this->config->isDebug()) {
            $options .= 'v';
        }

        $shell = getenv('SHELL');
        if (! $shell) {
            $shell = '/bin/sh';
        }

        $command = is_string($command) ? $command : implode(' ', $command);

        return array_merge(['/opt/homebrew/bin/shadowenv', 'exec', '--'], [$shell, "-$options", $command]);
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

        /**
         * We will use process without ShadowEnv or environment injection to prevent
         * infinite recursion. Besides, we don't need to inject any environment variables
         * or use ShadowEnv.
         */
        $result = Process::run([$shell['bin'], '-c', "(source {$shell['profile']} && command -v __shadowenv_hook) >/dev/null 2>&1"]);
        $hookInstalled = $binaryInstalled = $this->usingShadowEnv = $result->successful();

        /**
         * It's highly unlikely that the hook will be installed and the binary not be installed.
         * So, we will return true if the hook is installed to save extra checks.
         */
        if ($hookInstalled) {
            return [$hookInstalled, $binaryInstalled];
        }

        $binaryInstalled = Process::run(['command', '-v', 'shadowenv'])->successful();

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
        $command = $this->createShadowEnvCommand($command);
        if (is_string($command)) {
            return SymfonyProcess::fromShellCommandline($command, $path ?? $this->config->cwd(), $this->environment($env), timeout: 0);
        }

        return new SymfonyProcess($command, $path ?? $this->config->cwd(), $this->environment($env), timeout: 0);
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
