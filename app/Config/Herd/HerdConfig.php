<?php

namespace App\Config\Herd;

use App\Contracts\ConfigInterface;
use App\Step\Herd\LockPhpStep;
use App\Step\StepInterface;
use Exception;

class HerdConfig implements ConfigInterface
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

        return $steps;
    }

    /**
     * @throws Exception
     */
    private function makeStep(string $name, mixed $config): StepInterface|ConfigInterface
    {
        return match ($name) {
            'sites' => new Sites($config),
            'php' => is_array($config) ? new PhpConfig($config) : new LockPhpStep($config),
            default => throw new Exception("Unknown step: $name"),
        };
    }
}
