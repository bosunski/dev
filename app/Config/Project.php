<?php

namespace App\Config;

use App\Dev;
use App\Exceptions\UserException;
use App\Factory;
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Illuminate\Support\Collection;

class Project
{
    protected readonly Config $config;

    public function __construct(public readonly Dev $dev)
    {
        $this->config = $dev->config;
    }

    public function services(): Collection
    {
        return collect($this->config->services())->unique()->map(function (string $service) {
            if ($service === $this->config->serviceName()) {
                throw new UserException('You cannot reference the current service in its own config!');
            }

            return new Project(Factory::create($this->dev->io(), Config::fromServiceName($service)));
        });
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

    protected function processServe(array $serves): array
    {
        $processes = [];
        foreach ($serves as $name => $serve) {
            /**
             * If the serve is a string, we assume it's the command to run.
             * We wrap it in an array to make it easier to work with.
             *
             * @example
             * 'serve' => 'php -S localhost:8000 -t public'
             * becomes
             * 'serve' => ['run' => 'php -S localhost:8000 -t public']
             */
            if (is_string($serve)) {
                $serve = ['run' => $serve];
            }

            $processes[] = [
                'name'     => $name,
                'project'  => $this->config->getName(),
                'instance' => $this->dev->runner->process($serve['run'], $this->config->cwd(), $this->getEnv($serve['env'] ?? '.env')),
            ];
        }

        return $processes;
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
                throw new UserException("File $file does not exist in {$this->dev->config->cwd()}.");
            }

            return [];
        }

        try {
            return Dotenv::parse(file_get_contents($this->dev->config->cwd($file)));
        } catch (InvalidFileException) {
            throw new UserException("Failed to parse $file. Please check the file for syntax errors.");
        }
    }
}
