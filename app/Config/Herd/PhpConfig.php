<?php

namespace App\Config\Herd;

use App\Config\Php\ExtensionConfig;
use App\Contracts\ConfigInterface;
use App\Step\Herd\LockPhpStep;
use App\Step\StepInterface;
use Exception;

class PhpConfig implements ConfigInterface
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
            $steps[] = $this->makeStep($name, $config);
        }

        return $steps;
    }

    /**
     * @throws Exception
     */
    private function makeStep(string $name, mixed $config): StepInterface|ConfigInterface
    {
        return match ($name) {
            'version'    => new LockPhpStep($config),
            'extensions' => new ExtensionConfig($config, []),
            default      => throw new Exception("Unknown step: $name"),
        };
    }
}
