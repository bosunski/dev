<?php

namespace App\Config;

use Amp\Process\Process;
use Amp\Process\ProcessException;
use App\Exceptions\UserException;
use App\Process\Pool;
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
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
                throw new UserException('You cannot reference the current service in its own config!');
            }

            return new Project(Config::fromServiceName($service));
        });
    }

    /**
     * @throws ProcessException
     */
    public function servicePool(Pool $pool): Pool
    {
        $this->services()->each(function (Project $service) use ($pool): void {
            $service->servicePool($pool);
            $this->addStatProcesses($service, $pool);
        });

        if ($this->config->isRoot) {
            $this->addStatProcesses($this, $pool);
        }

        return $pool;
    }

    public function getServe(?Collection $collector = null): Collection
    {
        if ($collector === null) {
            $collector = collect();
        }

        $this->services()->each(function (Project $service) use ($collector): void {
            $service->getServe($collector);
        });

        $serve = $this->config->getServe();

        if (empty($serve)) {
            return $collector;
        }

        $collector->put(
            $this->config->getName(),
            $this->processServe($serve)
        );

        return $collector;
    }

    protected function processServe(array $serve): array
    {
        $processesServe = [];
        foreach ($serve as $name => $command) {
            if (is_string($command)) {
                $processesServe[] = [
                    'name'    => $name,
                    'project' => $this->config->getName(),
                    'command' => $command,
                    'env'     => array_merge($this->getEnv(), $this->config->environment->toArray()),
                ];

                continue;
            }

            $env = array_merge(
                isset($command['env']) ? $this->getEnv($command['env']) : $this->getEnv(),
                $this->config->environment->toArray()
            );

            $processesServe[] = [
                'name'    => $name,
                'project' => $this->config->getName(),
                'command' => $command['run'] ?? [],
                'env'     => $env,
            ];
        }

        return $processesServe;
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

    private function getEnv(string|false $file = '.env'): array
    {
        if ($file === false) {
            return [];
        }

        $file = $file === '.env' ? '.env' : ".env.$file";
        $shouldThrowError = $file !== '.env';

        if (! file_exists($this->config->cwd($file))) {
            if ($shouldThrowError) {
                throw new UserException("File $file does not exist in {$this->config->cwd()}.");
            }

            return [];
        }

        try {
            return Dotenv::parse(file_get_contents($this->config->cwd($file)));
        } catch (InvalidFileException) {
            throw new UserException("Failed to parse $file. Please check the file for syntax errors.");
        }
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
