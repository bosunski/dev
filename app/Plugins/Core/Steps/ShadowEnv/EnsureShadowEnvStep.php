<?php

namespace App\Plugins\Core\Steps\ShadowEnv;

use App\Config\Config;
use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;

class EnsureShadowEnvStep implements Step
{
    protected bool $installed = false;

    protected bool $hookInstalled = false;

    public function name(): string
    {
        return 'Ensure ShadowEnv is Set Up';
    }

    public function run(Runner $runner): bool
    {
        /**
         * We are not sure that ShadowEnv is setup yet,
         * So, we will configure the runner to not use the ShadowEnv.
         */
        $runner = $runner->withoutShadowEnv();
        if (! $this->installed) {
            $installed = $runner->exec('brew install shadowenv');
            if (! $installed) {
                return false;
            }
        }

        if ($this->hookInstalled) {
            return true;
        }

        [$shellName,, $profile] = $this->resolve($runner->config());
        if (! is_file($profile)) {
            throw new UserException("Unable to find the profile file: $profile. Please setup Shadowenv manually.");
        }

        $updatedProfile = file_put_contents($profile, $this->config($shellName), FILE_APPEND) !== false;
        if (! $updatedProfile) {
            throw new UserException("Unable to update the profile file: $profile. Please setup Shadowenv manually.");
        }

        return $this->done($runner);
    }

    /**
     * Resolve the current shell, shell name, and profile file.
     *
     * @param Config $config
     * @return array{string, string, string}
     * @throws UserException
     */
    protected function resolve(Config $config): array
    {
        $shell = getenv('SHELL');
        if (! $shell) {
            throw new UserException('Unable to determine the current shell. Please setup Shadowenv manually.');
        }

        $shellName = basename($shell);
        $profile = $config->home($this->profile($shellName));

        return [$shellName, $shell, $profile];
    }

    protected function profile(string $shell): string
    {
        return match ($shell) {
            'bash'  => '.bash_profile',
            'zsh'   => '.zshrc',
            'fish'  => 'config.fish',
            default => throw new UserException("Unknown shell: $shell. Please setup Shadowenv manually."),
        };
    }

    protected function config(string $shell): string
    {
        /**
         * We are adding a new line before the eval command to ensure that it has an empty line before it.
         * Like this:
         *
         * # Shadow Env
         * eval "$(shadowenv init zsh)"
         */
        return PHP_EOL . view('shadowenv.eval', ['shell' => $shell])->render();
    }

    public function done(Runner $runner): bool
    {
        $runner = $runner->withoutShadowEnv();
        [,,$profile] = $this->resolve($runner->config());
        $this->hookInstalled = $runner->exec("(source $profile && command -v __shadowenv_hook) >/dev/null 2>&1");

        /**
         * It's highly unlikely that the hook will be installed and the binary not be installed.
         * So, we will return true if the hook is installed to save extra checks.
         */
        if ($this->hookInstalled) {
            return true;
        }

        $this->installed = $runner->exec('command -v shadowenv');

        return false;
    }

    public function id(): string
    {
        return 'shadowenv.setup';
    }
}
