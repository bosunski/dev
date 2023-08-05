<?php

namespace App\Config;

use App\Contracts\ConfigInterface;
use App\Step\CustomStep;
use App\Step\BrewStep;
use App\Step\LockPhpStep;
use App\Step\ShadowEnvStep;
use App\Step\StepInterface;
use Exception;

class UpConfig implements ConfigInterface
{
    public function __construct(protected readonly array $steps)
    {
    }

    /**
     * @return array<int, StepInterface>
     * @throws Exception
     */
    public function steps(): array
    {
        $steps = [new LockPhpStep(), new ShadowEnvStep()];
        foreach ($this->steps as $step) {
            foreach ($step as $name => $args) {
                $configOrStep = $this->makeStep($name, $args);

                if ($configOrStep instanceof ConfigInterface) {
                    $steps = [...$steps, ...$configOrStep->steps()];
                    continue;
                }

                $steps[] = $configOrStep;
            }
        }

        return $steps;
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
            'custom' => new CustomStep($config),
            default => throw new Exception("Unknown step: $name"),
        };
    }
}
