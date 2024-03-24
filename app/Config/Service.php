<?php

namespace App\Config;

use App\Plugin\Contracts\Step;
use Exception;
use Illuminate\Support\Collection;

class Service
{
    public readonly string $id;

    public readonly Collection $steps;

    /**
     * @throws Exception
     */
    public function __construct(public readonly Config $config)
    {
        $this->id = $config->serviceName();
        $this->steps = collect();
    }

    /**
     * @throws Exception
     */
    private function steps(): array
    {
        return $this->config->up()->steps();
    }

    /**
     * @throws Exception
     */
    public function addSteps(callable $add): void
    {
        foreach ($this->steps() as $step) {
            $add($this->id, $step);
        }
    }

    public function add(Step $step): void
    {
        $this->steps->put($step->id(), $step);

    }

    public function hasStep(string $id): bool
    {
        return $this->steps->has($id);
    }
}
