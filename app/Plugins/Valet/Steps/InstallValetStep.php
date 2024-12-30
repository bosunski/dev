<?php

namespace App\Plugins\Valet\Steps;

use App\Config\Config;
use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;

use function Illuminate\Filesystem\join_paths;

class InstallValetStep implements Step
{
    protected string $valetBinary = 'vendor/bin/valet';

    protected bool $installed = false;

    protected bool $metRequirements = false;

    public function name(): string
    {
        return 'Install Laravel Valet';
    }

    public function run(Runner $runner): bool
    {
        if (! $this->metRequirements) {
            $this->ensureRequirements($runner);
        }

        $installed = $runner->exec("composer global require {$this->valetpackage($runner->config)} && $this->valetBinary install");
        if (! $installed) {
            return false;
        }

        // trust command isn't available on Linux
        return $runner->config->isLinux() || $runner->exec("$this->valetBinary trust");
    }

    private function ensureRequirements(Runner $runner): void
    {
        if ($runner->config->isDarwin()) {
            return;
        }

        if ($runner->config->isLinux()) {
            $this->ensureLinuxRequirements($runner);
        }
    }

    private function ensureLinuxRequirements(Runner $runner): void
    {
        $runner->io()->info('Installing required packages for Valet on Linux');
        // Check if distro is Ubuntu or Debian
        if (! $runner->hasCommand('apt-get')) {
            throw new UserException('Valet is only supported on Ubuntu or Debian');
        }

        $runner->exec('sudo apt-get install network-manager libnss3-tools jq xsel');
    }

    private function valetPackage(Config $config): string
    {
        return match(true) {
            $config->isDarwin() => 'laravel/valet',
            $config->isLinux()  => 'cpriego/valet-linux',
            default             => throw new UserException('Valet is not supported on this platform: ' . $config->platform()),
        };
    }

    public function done(Runner $runner): bool
    {
        $this->valetBinary = $this->valetBinPath($runner);

        $this->installed = is_file($this->valetBinary);
        $this->metRequirements = $this->metRequirements($runner);

        return $this->installed && $this->metRequirements;
    }

    private function metRequirements(Runner $runner): bool
    {
        if ($runner->config->isDarwin()) {
            return true;
        }

        if ($runner->config->isLinux()) {
            foreach(['jq', 'xsel', 'certutil'] as $command) {
                if (! $runner->hasCommand($command)) {
                    return false;
                }
            }

            return true;
        }

        return false;
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
