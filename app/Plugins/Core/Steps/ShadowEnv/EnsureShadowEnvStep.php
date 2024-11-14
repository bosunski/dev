<?php

namespace App\Plugins\Core\Steps\ShadowEnv;

use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Brew\Steps\BrewStep;

/**
 * @see https://shopify.github.io/shadowenv/getting-started
 */
class EnsureShadowEnvStep implements Step
{
    private bool $installed = false;

    private bool $hookInstalled = false;

    public function name(): string
    {
        return 'Ensure ShadowEnv is Set Up';
    }

    public function run(Runner $runner): bool
    {
        dump('Running EnsureShadowEnvStep');
        /**
         * We are not sure that ShadowEnv is setup yet,
         * So, we will configure the runner to not use the ShadowEnv.
         */
        if (! $this->installed) {
            $installed = $runner->withoutShadowEnv()->execute(new BrewStep(['shadowenv'])) === 0;
            if (! $installed) {
                dump('Issue');

                return false;
            }
        }

        if ($this->hookInstalled) {
            return true;
        }

        $shell = $runner->shell(null);
        if (! $shell) {
            throw new UserException('Unable to determine the current shell. Make sure you are using one of the supported shells: bash, zsh, fish.');
        }

        if (! is_file($shell['profile'])) {
            throw new UserException("Unable to find the profile file: {$shell['profile']}. Please setup Shadowenv manually.");
        }

        $updatedProfile = file_put_contents($shell['profile'], $this->config($shell['name']), FILE_APPEND) !== false;
        if (! $updatedProfile) {
            throw new UserException("Unable to update the profile file: {$shell['profile']}. Please setup Shadowenv manually.");
        }

        return $this->done($runner);
    }

    private function config(string $shell): string
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
        dump('Running done');
        [$hookInstalled, $binaryInstalled] = $runner->checkShadowEnv(true);
        if ($hookInstalled) {
            return $this->hookInstalled = true;
        }

        $this->installed = $binaryInstalled;

        return false;
    }

    public function id(): string
    {
        return 'shadowenv.setup';
    }
}
