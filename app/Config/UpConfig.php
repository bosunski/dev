<?php

namespace App\Config;

use App\Config\Config as DevConfig;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;
use App\Step\StepInterface;
use Exception;
use Illuminate\Support\Collection;

/**
 * @phpstan-import-type Up from DevConfig
 */
class UpConfig implements Config
{
    /**
     * @param Up $steps
     * @return void
     */
    public function __construct(protected array $steps = [])
    {
    }

    /**
     * @param array<non-empty-string, StepResolverInterface> $resolvers
     * @return Step[]
     * @throws Exception
     */
    public function steps(array $resolvers = []): array
    {
        /**
         * @var Collection<int, Step> $steps
         */
        $steps = collect();
        foreach ($this->steps as $step) {
            foreach ($step as $name => $args) {
                if (isset($resolvers[$name])) {
                    $configOrStep = $resolvers[$name]->resolve($args);
                } else {
                    $configOrStep = $this->makeStep($name, $args);
                }

                if ($configOrStep instanceof Config) {
                    $steps = $steps->merge($this->resolveStepFromConfig($configOrStep));

                    continue;
                }

                $steps->push($configOrStep);
            }
        }

        return $steps->all();
    }

    /**
     * @param non-empty-string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        foreach($this->steps as $step) {
            foreach ($step as $name => $args) {
                if ($name === $key) {
                    return $args;
                }
            }
        }

        return null;
    }

    /**
     * @param Config $config
     * @return Collection<int, StepInterface>
     */
    private function resolveStepFromConfig(Config $config): Collection
    {
        $steps = collect();
        foreach ($config->steps() as $configOrStep) {
            if ($configOrStep instanceof Config) {
                $steps = $steps->merge($this->resolveStepFromConfig($configOrStep));

                continue;
            }

            $steps->push($configOrStep);
        }

        return $steps;
    }

    /**
     * @throws Exception
     */
    private function makeStep(string $name, mixed $config): Step|Config
    {
        return match ($name) {
            default => throw new Exception("Unknown step: $name")
        };
    }
}
