<?php

namespace App\Plugins\Composer\Steps;

use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;

class EnsureComposerStep implements Step
{
    private bool $php = false;

    private bool $composer = false;

    public function id(): string
    {
        return 'composer.ensure';
    }

    public function name(): string
    {
        return 'Ensure Composer is installed';
    }

    public function run(Runner $runner): bool
    {
        if (! $this->php) {
            throw new UserException('Attempted to install Composer but it seems PHP is not installed or not in the PATH.');
        }

        $bin = $runner->config->globalBinPath('composer');

        return $runner->exec("/usr/bin/curl --fail --location --progress-bar --output $bin  https://github.com/composer/composer/releases/latest/download/composer.phar")
                && $runner->exec("chmod +x $bin");
    }

    public function done(Runner $runner): bool
    {
        $this->php = $runner->process('command -v php')->run()->successful();
        $this->composer = $runner->process('command -v composer')->run()->successful();

        return $this->php && $this->composer;
    }
}
