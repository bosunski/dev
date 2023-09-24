<?php

namespace App\Step\Nginx;

use App\Execution\Runner;
use App\Step\CanBeDeferred;

class NginxRestartStep implements CanBeDeferred
{
    public function name(): string
    {
        return 'Restarting Nginx';
    }

    public function command(): ?string
    {
        return 'valet restart nginx';
    }

    public function checkCommand(): ?string
    {
        return 'sudo brew services list | grep nginx';
    }

    public function run(Runner $runner): bool
    {
        return $runner->exec($this->command());
    }

    public function done(Runner $runner): bool
    {
        return false;
    }
}
