<?php

namespace App\Plugins\Composer\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Brew\Steps\BrewStep;

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
        return $runner->execute(new BrewStep(['composer']));
    }

    public function done(Runner $runner): bool
    {
        return $runner->exec('command -v composer');
    }
}
