<?php

namespace App\Step\Composer;

use App\Execution\Runner;
use App\Step\StepInterface;
use Exception;

class PackagesStep implements StepInterface
{
    public function __construct(private readonly array $packages)
    {
    }

    public function name(): string
    {
        return "Install global composer packages: {$this->formatPackages(', ')}";
    }

    private function formatPackages(string $glue = " "): string
    {
        return collect($this->packages)
            ->map(function ($package) {
                if (is_array($package)) {
                    foreach ($package as $name => $version) {
                        return "$name:'$version'";
                    }
                }

                if (is_string($package)) {
                    return $package;
                }

                throw new Exception("Unknown package format: $package");
            })->join($glue);
    }

    public function command(): ?string
    {
        return "composer global require {$this->formatPackages()}";
    }

    public function checkCommand(): ?string
    {
        return $this->command();
    }

    public function run(Runner $runner): bool
    {
        return $runner->exec($this->command());
    }

    public function done(Runner $runner): bool
    {
        return $runner->exec($this->checkCommand());
    }
}
