<?php

namespace App\Plugins\Brew\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;

class BrewStep implements Step
{
    public function __construct(private readonly array $packages)
    {
    }

    public function name(): string
    {
        $packages = implode(', ', $this->packages);

        return "Install brew packages: $packages";
    }

    public function command(): ?string
    {
        return 'brew install ' . implode(' ', $this->packages);
    }

    public function checkCommand(): ?string
    {
        return $this->command();
    }

    public function run(Runner $runner): bool
    {
        return $runner->exec($this->command(), env: [
            'HOMEBREW_NO_AUTO_UPDATE' => '1',
        ]);
    }

    public function done(Runner $runner): bool
    {
        return $runner->exec($this->checkCommand());
    }

    public function id(): string
    {
        return "brew.packages.{$this->formatPackages('_')}";
    }

    private function formatPackages(string $glue = ' '): string
    {
        return collect($this->packages)->join($glue);
    }
}
