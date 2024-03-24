<?php

namespace App\Plugins\Valet\Config;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Steps\LockPhpStep;
use Exception;

class ValetConfig implements Config
{
    public function __construct(protected readonly array $config, protected array $environment)
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
    private function makeStep(string $name, mixed $config): Step|Config
    {
        return match ($name) {
            'sites' => new Sites($config),
            'php'   => is_array($config) ? new PhpConfig($config, $this->environment['php']) : new LockPhpStep($config, $this->environment['php']),
            default => throw new Exception("Unknown step: $name"),
        };
    }
}
