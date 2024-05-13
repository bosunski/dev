<?php

namespace App\Plugins\Valet\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Exception;

class InstallValetStep implements Step
{
    public function __construct(protected string $composerBinary, protected string $valetBinary)
    {
    }

    public function name(): string
    {
        return 'Install Laravel Valet';
    }

    /**
     * @throws Exception
     */
    public function run(Runner $runner): bool
    {
        return $runner->exec("$this->composerBinary global require laravel/valet");
    }

    /**
     * @throws Exception
     */
    public function done(Runner $runner): bool
    {
        return is_file($this->valetBinary);
    }

    public function id(): string
    {
        return 'valet.install';
    }
}
