<?php

namespace App\Config;

use App\Config\Config as DevConfig;
use App\Config\Herd\HerdConfig;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;
use App\Plugins\Brew\Steps\BrewStep;
use App\Step\CustomStep;
use App\Step\Env\EnvSubstituteStep;
use App\Step\PHPStep;
use App\Step\Priority;
use App\Step\ShadowEnvStep;
use App\Step\StepInterface;
use Exception;

class UpConfig implements Config
{
    /**
     * @var array<non-empty-string, StepResolverInterface>
     */
    private array $stepResolvers = [];

    public function __construct(protected readonly DevConfig $config)
    {
    }

    public function addResolver(StepResolverInterface $resolver): void
    {
        $this->stepResolvers[$resolver->name()] = $resolver;
    }

    /**
     * @return array<int, StepInterface>
     *
     * @throws Exception
     */
    public function steps(): array
    {
        $steps = [];
        foreach ($this->config->steps() as $step) {
            foreach ($step as $name => $args) {
                if (isset($this->stepResolvers[$name])) {
                    $configOrStep = $this->stepResolvers[$name]->resolve($args);
                } else {
                    $configOrStep = $this->makeStep($name, $args);
                }

                if ($configOrStep instanceof Config) {
                    $steps = array_merge($steps, $this->resolveStepFromConfig($configOrStep));

                    continue;
                }

                $steps[] = $configOrStep;
            }
        }

        return collect($steps)
            ->sortBy($this->stepSorter(...))
            ->prepend(new EnvSubstituteStep($this->config))
            ->prepend(new ShadowEnvStep())
            ->toArray();
    }

    private function resolveStepFromConfig(Config $config): array
    {
        $steps = [];
        foreach ($config->steps() as $configOrStep) {
            if ($configOrStep instanceof Config) {
                $steps = array_merge($steps, $this->resolveStepFromConfig($configOrStep));

                continue;
            }

            $steps[] = $configOrStep;
        }

        return $steps;
    }

    private function stepSorter(Step $step): Priority
    {
        if ($step instanceof ShadowEnvStep || $step instanceof EnvSubstituteStep) {
            return Priority::HIGH;
        }

        return $step instanceof BrewStep ? Priority::HIGH : Priority::NORMAL;
    }

    /**
     * @throws Exception
     */
    private function makeStep(string $name, mixed $config): Step|Config
    {
        return match ($name) {
            'herd'     => new HerdConfig($config),
            'custom', 'script' => new CustomStep($config),
            'php'   => new PHPStep($config),
            default => throw new Exception("Unknown step: $name"),
        };
    }
}
