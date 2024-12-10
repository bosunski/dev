<?php

namespace App\Plugins\Valet\Steps;

use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;

use function Illuminate\Filesystem\join_paths;

class InstallValetStep implements Step
{
    protected string $valetBinary = 'vendor/bin/valet';

    public function name(): string
    {
        return 'Install Laravel Valet';
    }

    public function run(Runner $runner): bool
    {
        return $runner->exec("composer global require laravel/valet && $this->valetBinary install && $this->valetBinary trust");
    }

    public function done(Runner $runner): bool
    {
        $this->valetBinary = $this->valetBinPath($runner);

        return is_file($this->valetBinary);
    }

    private function valetBinPath(Runner $runner): string
    {
        $result = $runner->process('composer global config home')->run();
        if (! $result->successful()) {
            throw new UserException('Attempted to install Valet but it seems Composer is not installed or not in the PATH.');
        }

        return join_paths(trim($result->output()), $this->valetBinary);
    }

    public function id(): string
    {
        return 'valet.install';
    }
}
