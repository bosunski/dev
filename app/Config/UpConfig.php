<?php

namespace App\Config;

use App\Contracts\ConfigInterface;
use App\Exceptions\UserException;
use App\Step\CustomStep;
use App\Step\BrewStep;
use App\Step\Git\CloneStep;
use App\Step\LockPhpStep;
use App\Step\ShadowEnvStep;
use App\Step\StepInterface;
use App\Step\UpStep;
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

        foreach ($this->services() as $service) {
            $steps = [...$steps, ...$service];
        }

        $steps = [...$steps, new LockPhpStep(), new ShadowEnvStep()];

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

        return $steps;
    }

    /**
     * @throws Exception
     */
    private function services(): array
    {
        return collect($this->config->services())->map(function (string $service) {
            if ($service === $this->config->serviceName()) {
                throw new UserException("You cannot reference the current service in its own config!");
            }

            return [new CloneStep(...CloneStep::parseService($service)), new UpStep($this->config->sourcePath($service))];
        })->toArray();
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
