<?php

namespace App\Plugins\Brew\Steps;

use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;

class BrewStep implements Step
{
    /**
     * @var array<int, array{string, string}>
     */
    private array $installed = [];

    /**
     * @var string[]
     */
    private array $uninstalled = [];

    /**
     * @param string[] $packages
     * @return void
     */
    public function __construct(private readonly array $packages)
    {
    }

    public function name(): string
    {
        return 'Install brew formulae: ' . implode(', ', $this->packages);
    }

    private function brewBinPath(): string
    {
        return match (php_uname('s')) {
            'Darwin' => '/opt/homebrew/bin/brew',
            'Linux'  => '/home/linuxbrew/.linuxbrew/bin/brew',
            default  => throw new UserException('Unsupported OS: ' . php_uname('s')),
        };
    }

    public function run(Runner $runner): bool
    {
        return $runner->exec([$this->brewBinPath(), 'install', ...$this->packages], env: [
            'HOMEBREW_NO_AUTO_UPDATE'     => '1',
            'HOMEBREW_NO_INSTALL_UPGRADE' => '1',
        ]);
    }

    public function done(Runner $runner): bool
    {
        $installedPackages = $runner->withoutShadowEnv()->process([$this->brewBinPath(), 'list', '--formulae', '--versions'])->run()->throw()->output();
        $packages = array_filter(explode("\n", $installedPackages));
        // @phpstan-ignore-next-line
        $this->installed = array_map(fn (string $package) => explode(' ', $package), $packages);

        foreach ($this->packages as $package) {
            if (! $this->isInstalled($package)) {
                $this->uninstalled[] = $package;
            }
        }

        return empty($this->uninstalled);
    }

    private function isInstalled(string $package): bool
    {
        return collect($this->installed)->contains(fn (array $installed) => $installed[0] === $package);
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
