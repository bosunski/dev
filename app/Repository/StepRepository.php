<?php

namespace App\Repository;

use App\Config\Config;
use App\Config\Service;
use App\Exceptions\UserException;
use App\Step\CanBeDeferred;
use App\Step\DeferredStep;
use App\Step\StepInterface;
use Exception;

class StepRepository
{
    /**
     * @var array<string, Service> $services
     */
    protected array $services;

    /**
     * @throws UserException
     * @throws Exception
     */
    public function __construct()
    {
        $this->services = ['deferred' => new Service(Config::fromServiceName('deferred'))];
    }

    /**
     * @throws UserException
     */
    public function add(string $service, StepInterface $step): StepInterface
    {
        /**
         * We don't want to add the same step twice. This can happen if the SAME step is
         * referenced in multiple services. A step is uniquely identified by its ID.
         * This ID can be based on the parameters of the steps, so we can easily identify
         * steps that are bound to achieve the same thing.
         */
        if ($this->hasStep($step->id())) {
            // ToDo: Add a Skip step here that will just log that the step was skipped
            return $step;
        }

        if ($step instanceof CanBeDeferred) {
            $step = new DeferredStep($this, $step);
        }

        if (! $this->services[$service] ?? false) {
            throw new UserException("Service $service does not exist!");
        }

        $this->services[$service]->add($step);

        return $step;
    }

    /**
     * @throws UserException
     */
    public function addDeferred(DeferredStep $deferred): StepInterface
    {
        /**
         * ToDo: Use self::add() here instead of duplicating the code
         */
        $this->add('deferred', $deferred->step());

        return $deferred;
    }

    public function hasStep(string $id): bool
    {
        return collect($this->services)
            ->map(fn (Service $service) => $service->hasStep($id))
            ->contains(true);
    }

    /**
     * @throws Exception
     */
    public function addService(Service $service): void
    {
        $this->services[$service->id] = $service;
        /**
         * We are adding the steps through this repository to ensure there is
         * no duplicate across services.
         */
        $service->addSteps($this->add(...));
    }

    public function hasService(string $id): bool
    {
        return collect($this->services)->has($id);
    }

    public function getService(string $id): ?Service
    {
        return $this->services[$id] ?? null;
    }

    public function getServices(): array
    {
        $deferred = array_shift($this->services);
        $this->services['deferred'] = $deferred;

        return $this->services;
    }
}
