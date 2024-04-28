<?php

namespace App\Plugins\Valet\Config;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Steps\LockPhpStep;
use Exception;

/**
 * @phpstan-import-type RawPhpConfig from ValetConfig
 * @phpstan-import-type RawValetEnvironment from ValetConfig
 */
class PhpConfig implements Config
{
    /**
     * @param RawPhpConfig $config
     * @param RawValetEnvironment $environment
     *
     * @return void
     */
    public function __construct(protected readonly array $config, protected array $environment)
    {
    }

    /**
     * @return array<int, Step|Config>
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
     * @template T of key-of<RawPhpConfig>
     * @param T $name
     * @param RawPhpConfig[T] $value
     * @throws Exception
     */
    private function makeStep(string $name, mixed $value): Step|Config
    {
        return match ($name) {
            'version'    => new LockPhpStep($value, $this->environment),
            'extensions' => new ExtensionConfig($value, $this->environment),
            default      => throw new Exception("Unknown step: $name"),
        };
    }
}
