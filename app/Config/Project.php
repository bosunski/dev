<?php

namespace App\Config;

use App\Dev;
use App\Exceptions\UserException;
use App\Factory;
use App\Plugin\Capability\ConfigProvider;
use App\Plugin\Contracts\Step;
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Exception;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * @phpstan-type ServeConfig array{name: string, project: string, instance: Process}
 * @phpstan-import-type Serve from Config
 */
class Project
{
    public readonly string $id;

    /**
     * @var Collection<string, Step>
     */
    public readonly Collection $steps;

    protected readonly Config $config;

    public function __construct(public readonly Dev $dev)
    {
        $this->config = $dev->config;
        $this->id = $dev->config->projectName();
        $this->steps = collect();
    }

    /**
     * @return Collection<int, Project>
     */
    protected function projects(?string $root = null): Collection
    {
        return $this->config->projects()->unique()->map(function (string $service) use ($root): Project {
            if ($service === $this->config->projectName()) {
                throw new UserException('You cannot reference the current service in its own config!');
            }

            return new Project(Factory::create($this->dev->io(), Config::fromProjectName($service, $root)));
        });
    }

    /**
     * @param null|Collection<string, ServeConfig[]> $collector
     * @return Collection<string, ServeConfig[]>
     * @throws UserException
     * @throws InvalidArgumentException
     */
    public function getServe(?Collection $collector = null): Collection
    {
        if ($collector === null) {
            $collector = collect();
        }

        $this->projects()->each(function (Project $service) use ($collector): void {
            $service->getServe($collector);
        });

        $serve = $this->config->getServe();
        if (empty($serve)) {
            return $collector;
        }

        return $collector->put($this->config->getName(), $this->processServe($serve));
    }

    /**
     * @param array<string, Serve> $serves
     * @return ServeConfig[]
     * @throws UserException
     * @throws InvalidArgumentException
     */
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
                /**
                 * Sometimes we do get errors like "Class "Illuminate\Process\InvokedProcess" not found".
                 * when using the Illuminate\Process. That's why we are using the SymfonyProcess instead.
                 * The reason for this is yet to be known. But I have a hunch it has something to do with the
                 * fact that we are running DEV inside Coroutines.
                 */
                'instance' => $this->dev->runner->procProcess($serve['run'], $this->config->cwd(), $this->getEnv($serve['env'] ?? '.env')),
            ];
        }

        return $processes;
    }

    /**
     * Get envs from a .env.* file
     *
     * @param string|false $file
     * @return array<string, string|null>
     * @throws UserException
     */
    private function getEnv(string|false $file = '.env'): array
    {
        if ($file === false) {
            return [];
        }

        $file = $file === '.env' ? '.env' : ".env.$file";
        $shouldThrowError = $file !== '.env';

        if (! file_exists($path = $this->config->cwd($file))) {
            if ($shouldThrowError) {
                throw new UserException("File $file does not exist in {$this->dev->config->cwd()}.");
            }

            return [];
        }

        try {
            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException("Unable to retrieve file at $path");
            }

            return Dotenv::parse($content);
        } catch (InvalidFileException) {
            throw new UserException("Failed to parse $file. Please check the file for syntax errors.");
        }
    }

    /**
     * @return Collection<int, Step>
     * @throws Exception
     */
    public function steps(): Collection
    {
        $manager = $this->dev->getPluginManager();
        $resolvers = [];
        /** @var Collection<int, Step> $steps */
        $steps = collect();

        foreach ($manager->getCcs(ConfigProvider::class, ['dev' => $this->dev]) as $capability) {
            $newResolvers = $capability->stepResolvers();
            $steps = $steps->merge($capability->steps());
            foreach ($newResolvers as $name => $resolver) {
                $resolvers[$name] = $resolver;
            }
        }

        return $steps->merge($this->dev->config->up()->steps($resolvers));
    }

    public function add(Step $step): void
    {
        $this->steps->put($step->id(), $step);
    }

    public function hasStep(string $id): bool
    {
        return $this->steps->has($id);
    }

    public function runSteps(): int
    {
        return $this->dev->runner->execute($this->steps()->all(), true);
    }
}
