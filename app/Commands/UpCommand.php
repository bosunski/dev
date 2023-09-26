<?php

namespace App\Commands;

use App\Config\Config;
use App\Config\Service;
use App\Exceptions\UserException;
use App\Execution\Runner;
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
    protected $signature = 'up';

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
    public function __construct(protected readonly StepRepository $stepRepository)
    {
        parent::__construct();

        $this->config = Config::fromPath(getcwd());
        $this->runner = new Runner($this->config, $this);
    }

    /**
     * @throws Exception
     */
    public function handle(): int
    {
        if ($this->config->services()->count() > 0) {
            $this->info("🚀 Project contains {$this->config->services()->count()} services. Resolving all services...");
            $this->config->services()->each(fn ($service) => $this->resolveService($service));
        }

        $this->stepRepository->addService($this->config->service());

        $services = $this->stepRepository->getServices();

        foreach ($services as $service) {
            if ($service->steps->count() === 0) {
                continue;
            }

            $this->info("🚀 Running steps for $service->id...");
            $runner = new Runner($service->config, $this);
            if($runner->execute($service->steps->toArray()) !== 0) {
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
    private function resolveService(string $serviceName): Service
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
        if ($this->runner->execute([new CloneStep(...CloneStep::parseService($serviceName))]) !== 0) {
            throw new UserException("Failed to clone $serviceName");
        }

        $config = Config::fromServiceName($serviceName);
        if ($config->services()->isNotEmpty()) {
            $config->services()->each(fn ($service) => $this->resolveService($service));
        }

        $this->stepRepository->addService($service = new Service(Config::fromServiceName($serviceName)));

        return $service;
    }
}
