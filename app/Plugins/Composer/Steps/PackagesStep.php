<?php

namespace App\Plugins\Composer\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Composer\Config\ComposerConfig;
use Exception;

/**
 * @phpstan-import-type RawComposerConfig from ComposerConfig
 */
class PackagesStep implements Step
{
    /**
     * @param RawComposerConfig['packages'] $packages
     * @return void
     */
    public function __construct(private readonly array $packages)
    {
    }

    public function id(): string
    {
        return "composer.packages.{$this->formatPackages('_')}";
    }

    public function name(): string
    {
        return "Install global composer packages: {$this->formatPackages(', ')}";
    }

    private function formatPackages(string $glue = ' '): string
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

                $var = var_export($package, true);

                throw new Exception("Unknown package format: $var");
            })->join($glue);
    }

    public function run(Runner $runner): bool
    {
        return $runner->exec("composer global require {$this->formatPackages()}");
    }

    public function done(Runner $runner): bool
    {
        return false;
    }
}
