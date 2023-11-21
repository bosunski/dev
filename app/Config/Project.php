<?php

namespace App\Config;

use Amp\Process\Process;
use Amp\Process\ProcessException;
use App\Exceptions\UserException;
use App\Process\Pool;
use Dotenv\Dotenv;
use Illuminate\Support\Collection;

class Project
{
    public function __construct(public readonly Config $config)
    {
    }

    public function services(): Collection
    {
        return collect($this->config->services())->unique()->map(function (string $service) {
            if ($service === $this->config->serviceName()) {
                throw new UserException("You cannot reference the current service in its own config!");
            }

            return new Project(Config::read(Config::sourcePath($service)));
        });
    }

    /**
     * @throws ProcessException
     */
    public function servicePool(Pool $pool): Pool
    {
        $this->services()->each(function (Project $service) use ($pool) {
            $service->servicePool($pool);
            $this->addStatProcesses($service, $pool);
        });

        if ($this->config->isRoot) {
            $this->addStatProcesses($this, $pool);
        }

        return $pool;
    }

    /**
     * @throws ProcessException
     */
    private function addStatProcesses(Project $service, Pool $pool): void
    {
        if (! $service->hasProcfile()) {
            return;
        }

        $envContent = '';

        foreach (getenv() as $key => $value) {
            $envContent .= "$key='$value'\n";
        }

        if ($service->hasEnvFile()) {
            $envContent .= @file_get_contents($service->config->cwd('.env')) ?? '';
        }

        $envs = collect(Dotenv::parse($envContent));
        $pool->add($service->config->serviceName(), Process::start('shadowenv exec -- /opt/homebrew/bin/hivemind', $service->config->cwd(), $envs->toArray()));
    }

    public function hasProcfile(): bool
    {
        return file_exists($this->config->cwd('Procfile'));
    }

    public function hasEnvFile(): bool
    {
        return file_exists($this->config->cwd('.env'));
    }
}
