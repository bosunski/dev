<?php

namespace App\Config;

use App\Contracts\ConfigInterface;
use App\Step\CustomStep;
use App\Step\BrewStep;
use App\Step\Env\EnvCopyStep;
use App\Step\Env\EnvSubstituteStep;
use App\Step\LockPhpStep;
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
//        if ($phpVersion = $this->config->getPhp()) {
//            $steps[] = new LockPhpStep($phpVersion);
//        }

        $steps = [...$steps, new ShadowEnvStep()];

        if ($this->shouldCopyEnv()) {
            $steps[] = new EnvCopyStep($this->config);
        } else {
            $steps[] = new EnvSubstituteStep($this->config);
        }

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

    private function shouldCopyEnv(): bool
    {
        return ! is_file($this->config->cwd('.env'))
                && is_file($this->config->cwd('.env.example'));
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
