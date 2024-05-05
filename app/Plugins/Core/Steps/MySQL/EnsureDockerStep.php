<?php

namespace App\Plugins\Core\Steps\MySQL;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;

class EnsureDockerStep implements Step
{
    protected bool $orbStackPowered = false;

    protected bool $usingOrbStackContext = false;

    protected bool $orbStackInstalled = false;

    protected bool $orbStackRunning = false;

    protected bool $hasCorrectOrbStackVersion = false;

    public function id(): string
    {
        return 'mysql-ensure-docker';
    }

    public function name(): ?string
    {
        return 'Ensure Docker is setup for MySQL';
    }

    public function run(Runner $runner): bool
    {
        if (! $this->orbStackInstalled && ! $this->installOrbStack($runner)) {
            return false;
        }

        if (! $this->hasCorrectOrbStackVersion && ! $this->upgradeOrbStack($runner, '1.5.1')) {
            return false;
        }

        if (! $this->orbStackRunning && ! $this->startOrbStack($runner)) {
            return false;
        }

        if (! $this->usingOrbStackContext && ! $this->switchDockerContext($runner)) {
            return false;
        }

        return $this->isOrbStackPowered($runner);
    }

    protected function switchDockerContext(Runner $runner): bool
    {
        return $runner->exec('docker context use orbstack');
    }

    protected function upgradeOrbStack(Runner $runner, string $newVersion): bool
    {
        if (! $runner->io()->confirm("OrbStack needs to be upgraded to version $newVersion, do you want to proceed?")) {
            return false;
        }

        return $runner->exec('brew upgrade --greedy orbstack', env: [
            'HOMEBREW_NO_AUTO_UPDATE' => '1',
        ]);
    }

    protected function startOrbStack(Runner $runner): bool
    {
        return $runner->exec('orbctl start');
    }

    protected function installOrbStack(Runner $runner): bool
    {
        if (! $runner->io()->confirm('Using database feature depends on OrbStack, do you want to install it?')) {
            return false;
        }

        return $runner->exec('brew install orbstack', env: [
            'HOMEBREW_NO_AUTO_UPDATE' => '1',
        ]);
    }

    public function done(Runner $runner): bool
    {
        /**
         * We need to check if OrbStack is installed and running. We also
         * need to check if the current docker engine is powered by OrbStack.
         * In Summary, we will check:
         * - If OrbStack is installed
         * - If OrbStack is running
         * - If the current docker engine is powered by OrbStack
         */
        // if ($this->isOrbStackPowered($runner)) {
        //     $this->markAllCompleted();

        //     return true;
        // }

        $orbstackInstalled = $this->orbStackInstalled = ($result = $runner->process('orbctl version')->run())->successful();
        if (! $orbstackInstalled) {
            return false;
        }

        $output = $result->output();
        preg_match('/^Version: (?<version>\d+.\d+.\d)/', $output, $matches);
        $orbstackVersion = $matches[1] ?? '0.0.0';
        if (version_compare($orbstackVersion, '1.5.1', '<')) {
            return false;
        }

        $this->hasCorrectOrbStackVersion = true;

        return $this->orbStackRunning = ($runner->process('orbctl status')->run()->output() === 'Running');
    }

    protected function isOrbStackPowered(Runner $runner): bool
    {
        $json = ($result = $runner->process('docker info --format=json')->run())->output();
        if (! $result->successful()) {
            return false;
        }

        if (! $info = json_decode($json, true)) {
            return false;
        }

        return data_get($info, 'ClientInfo.Context') === 'orbstack';
    }

    protected function markAllCompleted(): void
    {
        $this->orbStackInstalled = true;
        $this->orbStackRunning = true;
        $this->usingOrbStackContext = true;
        $this->hasCorrectOrbStackVersion = true;
    }
}
