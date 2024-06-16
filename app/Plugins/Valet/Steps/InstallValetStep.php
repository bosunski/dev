<?php

namespace App\Plugins\Valet\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;

class InstallValetStep implements Step
{
    public function __construct(protected string $composerBinary, protected string $valetBinary)
    {
    }

    public function name(): string
    {
        return 'Install Laravel Valet';
    }

    public function run(Runner $runner): bool
    {
        return $runner->exec("$this->composerBinary global require laravel/valet && $this->valetBinary install");
    }

    public function done(Runner $runner): bool
    {
        return is_file($this->valetBinary);
    }

    public function id(): string
    {
        return 'valet.install';
    }
}
