<?php

namespace App\Plugins\Spc\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;

class SpcInstallStep implements Step
{
    public function id(): string
    {
        return 'spc-install';
    }

    public function name(): string
    {
        return 'Install SPC binary';
    }

    public function run(Runner $runner): bool
    {
        $binDir = $runner->config()->path('bin');

        $filename = 'spc-macos-aarch64.tar.gz';
        $script = <<<SCRIPT
            mkdir -p $binDir
            gh release --repo crazywhalecc/static-php-cli download -p $filename
            tar -xvf $filename
            chmod +x spc
            rm $filename
        SCRIPT;

        return $runner->exec($script, $runner->config()->globalPath('bin'));
    }

    public function done(Runner $runner): bool
    {
        return is_file($runner->config()->globalPath('bin/spc'));
    }
}
