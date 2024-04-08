<?php

namespace App\Repository;

use App\Config\Project;
use App\Dev;
use App\Exceptions\UserException;
use App\Plugin\Contracts\Step;
use App\Step\CanBeDeferred;
use App\Step\DeferredStep;
use Exception;
use RuntimeException;

class StepRepository
{
    /**
     * @var array<string, Project>
     */
    protected array $services;

    /**
     * @throws UserException
     * @throws Exception
     */
    public function __construct(Dev $dev)
    {
        $this->services = [
            'deferred' => new Project($dev),
        ];
    }

    /**
     * @throws UserException
     */
    public function add(string $service, Step $step): Step
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

        if (! ($this->services[$service] ?? false)) {
            throw new UserException("Service $service does not exist!");
        }

        $this->services[$service]->add($step);

        return $step;
    }

    /**
     * @throws UserException
     */
    public function addDeferred(DeferredStep $deferred): Step
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
            ->map(fn (Project $service) => $service->hasStep($id))
            ->contains(true);
    }

    /**
     * @throws Exception
     */
    public function addProject(Project $service): void
    {
        $this->services[$service->id] = $service;
        /**
         * We are adding the steps through this repository to ensure there is
         * no duplicate across services.
         */
        $service->addSteps($this->add(...));
    }

    public function getProject(string $id): ?Project
    {
        return $this->services[$id] ?? null;
    }

    /**
     * @return array<string, Project>
     */
    public function getProjects(): array
    {
        $deferred = array_shift($this->services);
        if (! $deferred) {
            throw new RuntimeException('Expect to find default defered project but found none.');
        }

        /**
         * Put the deferred project as the last in the list of projects
         */
        $this->services['deferred'] = $deferred;

        return $this->services;
    }
}
