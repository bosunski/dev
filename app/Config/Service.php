<?php

namespace App\Config;

use App\Dev;
use App\Plugin\Capability\ConfigProvider;
use App\Plugin\Contracts\Step;
use Exception;
use Illuminate\Support\Collection;

class Service
{
    public readonly string $id;

    /**
     * @var Collection<string, Step>
     */
    public readonly Collection $steps;

    /**
     * @throws Exception
     */
    public function __construct(public readonly Dev $dev)
    {
        $this->id = $dev->config->projectName();
        $this->steps = collect();
    }

    /**
     * @return Collection<int, Step>
     * @throws Exception
     */
    private function steps(): Collection
    {
        $manager = $this->dev->getPluginManager();
        $resolvers = [];
        /** @var Collection<int, Step> $steps */
        $steps = collect();
        foreach ($manager->getCcs(ConfigProvider::class, [$this->dev]) as $capability) {
            $newResolvers = $capability->stepResolvers();
            $steps = $steps->merge($capability->steps());
            foreach ($newResolvers as $name => $resolver) {
                $resolvers[$name] = $resolver;
            }
        }

        return $steps->merge($this->dev->config->up()->steps($resolvers));
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
        return $this->dev->runner->execute($this->steps->all());
    }
}
