<?php

namespace App\Config;

use App\Dev;
use App\Plugin\Capability\ConfigProvider;
use App\Plugin\Contracts\Step;
use App\Step\ShadowEnvStep;
use Exception;
use Illuminate\Support\Collection;

class Service
{
    public readonly string $id;

    public readonly Collection $steps;

    /**
     * @throws Exception
     */
    public function __construct(public readonly Dev $dev)
    {
        $this->id = $dev->config->serviceName();
        $this->steps = collect();
    }

    /**
     * @throws Exception
     */
    private function steps(): array
    {
        $manager = $this->dev->getPluginManager();
        $resolvers = [];
        foreach ($manager->getCcs(ConfigProvider::class, [$this->dev]) as $capability) {
            $newResolvers = $capability->stepResolvers($this->dev);
            foreach ($newResolvers as $resolver) {
                $resolvers[$resolver->name()] = $resolver;
            }
        }

        return [new ShadowEnvStep($this->dev), ...$this->dev->config->up()->steps($resolvers)];
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

    public function runSteps(): int
    {
        return $this->dev->runner->execute($this->steps->toArray());
    }
}
