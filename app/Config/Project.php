<?php

namespace App\Config;

use Amp\Process\Process;
use Amp\Process\ProcessException;
use App\Exceptions\UserException;
use App\Process\Pool;
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

        $command = "hivemind --root {$service->config->cwd()} {$service->config->cwd('Procfile')}";
        $pool->add($service->config->serviceName(), Process::start($command));
    }

    public function hasProcfile(): bool
    {
        return file_exists($this->config->cwd('Procfile'));
    }

    public function hasGarmFile(): bool
    {
        return file_exists($this->config->cwd('.garm.yaml'));
    }
}
