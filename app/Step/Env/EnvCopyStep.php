<?php

namespace App\Step\Env;

use App\Config\Config;
use App\Execution\Runner;
use App\Step\StepInterface;

class EnvCopyStep implements StepInterface
{
    public function __construct(protected readonly Config $config)
    {
    }

    public function name(): string
    {
        return 'Copying .env file from discovered .env.example';
    }

    public function run(Runner $runner): bool
    {
        if ($this->hasEnvFile($runner->config())) {
            $runner->io()->info('Skipping .env file already exists');
            return true;
        }

        if (! $this->hasSampleEnvFile($runner->config())) {
            return true;
        }

        return copy($runner->config()->cwd('.env.example'), $runner->config()->cwd('.env'));
    }

    public function done(Runner $runner): bool
    {
        return $this->hasRequiredEnvFiles($runner->config());
    }

    private function hasSampleEnvFile(Config $config): bool
    {
        return is_file($config->cwd('.env.example'));
    }

    private function hasEnvFile(Config $config): bool
    {
        return is_file($config->cwd('.env'));
    }

    private function hasRequiredEnvFiles(Config $config): bool
    {
        return $this->hasSampleEnvFile($config) && $this->hasEnvFile($config);
    }

    public function id(): string
    {
        return "env-copy-{$this->config->cwd()}";
    }
}
