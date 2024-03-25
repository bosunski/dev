<?php

namespace App\Commands;

use App\Config\Config;
use App\Config\Service;
use App\Dev;
use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Factory;
use App\Repository\StepRepository;
use App\Step\Git\CloneStep;
use Exception;
use LaravelZero\Framework\Commands\Command;

class UpCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'up {--self : Skip Services}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Boostrap a project';

    protected readonly Config $config;

    protected readonly Runner $runner;

    /**
     * @throws UserException
     */
    public function __construct(protected readonly StepRepository $stepRepository, Dev $dev)
    {
        parent::__construct();

        $this->config = $dev->config;
        $this->runner = $dev->runner;
    }

    /**
     * @throws Exception
     */
    public function handle(Dev $dev): int
    {
        if (! $this->option('self') && $dev->config->services()->count() > 0) {
            $this->info("🚀 Project contains {$dev->config->services()->count()} services. Resolving all services...");
            $this->config->services()->each(fn ($service) => $this->resolveService($service, $this->config->path()));
        }

        $this->stepRepository->addService(new Service($dev));

        $services = $this->stepRepository->getServices();

        foreach ($services as $service) {
            if ($service->steps->count() === 0) {
                continue;
            }

            $this->info("🚀 Running steps for $service->id...");
            if ($service->runSteps() !== 0) {
                $this->error("⛔️ Failed to run steps for $service->id");

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @throws UserException
     * @throws Exception
     */
    private function resolveService(string $serviceName, string $root): Service
    {
        /**
         * First we check if the service is already in the repository.
         * If, so, this means its already been cloned, and we can just return it.
         * This will also eventually prevent infinite loops caused by circular dependencies.
         */
        if ($this->stepRepository->hasService($serviceName)) {
            return $this->stepRepository->getService($serviceName);
        }

        /**
         * If the service is not in the repository, we need to clone it.
         * We also need to resolve any dependencies it has.
         * ToDo: Handle error if the service does not exist or not clonable
         */
        [$owner, $repo] = CloneStep::parseService($serviceName);
        if ($this->runner->execute([new CloneStep($owner, $repo, 'github.com', ['--depth=1'], $root, true)]) !== 0) {
            throw new UserException("Failed to clone $serviceName");
        }

        $config = Config::fromServiceName($serviceName, $root);
        if ($config->services()->isNotEmpty()) {
            $config->services()->each(fn ($service) => $this->resolveService($service, $root));
        }

        $dev = Factory::create($this->runner->io(), Config::fromServiceName($serviceName));

        $this->stepRepository->addService($service = new Service($dev));

        return $service;
    }
}
