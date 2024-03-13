<?php

namespace App\Config;

use App\Config\Composer\AuthConfig;
use App\Contracts\ConfigInterface;
use App\Step\Composer\AuthStep;
use App\Step\Composer\PackagesStep;
use App\Step\StepInterface;
use Exception;

class ComposerConfig implements ConfigInterface
{
    public function __construct(protected readonly array $config)
    {
    }

    /**
     * @throws Exception
     */
    public function steps(): array
    {
        $steps = [];
        foreach ($this->config as $name => $config) {
            $configOrStep = $this->makeStep($name, $config);

            if ($configOrStep instanceof ConfigInterface) {
                $steps = [...$steps, ...$configOrStep->steps()];

                continue;
            }

            $steps[] = $configOrStep;
        }

        return collect($steps)
            ->sortBy(fn ($step) => $step instanceof AuthStep ? StepInterface::PRIORITY_HIGH : StepInterface::PRIORITY_NORMAL)
            ->toArray();
    }

    /**
     * @throws Exception
     */
    private function makeStep(string $name, array $config): StepInterface|ConfigInterface
    {
        return match ($name) {
            'auth'     => new AuthConfig($config),
            'packages' => new PackagesStep($config),
            default    => throw new Exception("Unknown step: $name"),
        };
    }
}
