<?php

namespace App\Config;

use App\Config\Herd\HerdConfig;
use App\Config\Valet\ValetConfig;
use App\Contracts\ConfigInterface;
use App\Step\BrewStep;
use App\Step\CustomStep;
use App\Step\Env\EnvSubstituteStep;
use App\Step\Priority;
use App\Step\ShadowEnvStep;
use App\Step\StepInterface;
use Exception;

class UpConfig implements ConfigInterface
{
    public function __construct(protected readonly Config $config)
    {
    }

    /**
     * @return array<int, StepInterface>
     * @throws Exception
     */
    public function steps(): array
    {
        $steps = [];
        foreach ($this->config->steps() as $step) {
            foreach ($step as $name => $args) {
                $configOrStep = $this->makeStep($name, $args);

                if ($configOrStep instanceof ConfigInterface) {
                    $steps = [...$steps, ...$configOrStep->steps()];
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

    private function stepSorter(StepInterface $step): Priority
    {
        if ($step instanceof ShadowEnvStep || $step instanceof EnvSubstituteStep) {
            return Priority::HIGH;
        }

        return $step instanceof BrewStep ? Priority::HIGH : Priority::NORMAL;
    }

    /**
     * @throws Exception
     */
    private function makeStep(string $name, mixed $config): StepInterface|ConfigInterface
    {
        return match ($name) {
            'composer' => new ComposerConfig($config),
            'brew' => new BrewStep($config),
            'herd' => new HerdConfig($config),
            'valet' => new ValetConfig($config, $this->config),
            'custom', 'script' => new CustomStep($config),
            default => throw new Exception("Unknown step: $name"),
        };
    }
}
