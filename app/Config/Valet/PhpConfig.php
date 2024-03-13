<?php

namespace App\Config\Valet;

use App\Config\Php\ExtensionConfig;
use App\Contracts\ConfigInterface;
use App\Step\StepInterface;
use App\Step\Valet\LockPhpStep;
use Exception;

class PhpConfig implements ConfigInterface
{
    public function __construct(protected readonly array $config, protected array $environment = [])
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
            'version'    => new LockPhpStep($config, $this->environment),
            'extensions' => new ExtensionConfig($config, $this->environment),
            default      => throw new Exception("Unknown step: $name"),
        };
    }
}
