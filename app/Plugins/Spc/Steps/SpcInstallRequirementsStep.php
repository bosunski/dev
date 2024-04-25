<?php

namespace App\Plugins\Spc\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;

class SpcInstallRequirementsStep implements Step
{
    public function id(): string
    {
        return 'spc-install-requirements';
    }

    public function name(): string
    {
        return 'Install SPC Requirements';
    }

    public function run(Runner $runner): bool
    {
        return $runner->exec('brew install cmake', env: [
            'HOMEBREW_NO_AUTO_UPDATE' => '1',
        ]);
    }

    public function done(Runner $runner): bool
    {
        return $runner->exec('cmake --version');
    }
}
