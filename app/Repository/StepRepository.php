<?php

namespace App\Repository;

use App\Config\Service;
use App\Step\CanBeDeferred;
use App\Step\DeferredStep;
use App\Step\StepInterface;
use Exception;

class StepRepository
{
    /**
     * @var array<string, array<string, StepInterface>> $services
     */
    protected array $services = ['deferred' => []];

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

        $this->services[$service][$step->id()] = $step;

        return $step;
    }

    public function addDeferred(DeferredStep $deferred): StepInterface
    {
        /**
         * ToDo: Use self::add() here instead of duplicating the code
         */
        if (! $this->services['deferred'][$deferred->id()] ?? false) {
            $this->services['deferred'][$deferred->id()] = $deferred->step();
        }

        return $deferred;
    }

    public function hasStep(string $id): bool
    {
        return collect($this->services)
            ->map(fn (array $steps) => collect($steps)->has($id))
            ->contains(true);
    }

    /**
     * @throws Exception
     */
    public function addService(Service $service): void
    {
        $this->services[$service->id] = [];

        foreach ($service->steps() as $step) {
            $this->add($service->id, $step);
        }
    }

    public function hasService(string $id): bool
    {
        return collect($this->services)->has($id);
    }

    public function getService(string $id): ?array
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
