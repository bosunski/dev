<?php

namespace App\Plugins\Valet\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;

class PrepareValetStep implements Step
{
    public function name(): string
    {
        return 'Prepare Laravel Valet';
    }

    public function run(Runner $runner): bool
    {
        $path = $runner->config()->globalPath('valet/sites');
        if (is_dir($path)) {
            return true;
        }

        return mkdir($path, recursive: true);
    }

    public function done(Runner $runner): bool
    {
        return is_dir($runner->config()->globalPath('valet/sites'));
    }

    public function id(): string
    {
        return 'valet.prepare';
    }
}
