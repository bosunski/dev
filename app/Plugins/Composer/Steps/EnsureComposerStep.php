<?php

namespace App\Plugins\Composer\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;

class EnsureComposerStep implements Step
{
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
        $bin = $runner->config->globalBinPath('composer');

        return $runner->exec("/usr/bin/curl --fail --location --progress-bar --output $bin  https://github.com/composer/composer/releases/latest/download/composer.phar")
                && $runner->exec("chmod +x $bin");
    }

    public function done(Runner $runner): bool
    {
        return $runner->exec('command -v composer');
    }
}
