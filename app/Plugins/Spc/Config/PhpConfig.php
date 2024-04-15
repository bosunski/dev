<?php

namespace App\Plugins\Spc\Config;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugins\Spc\Steps\LockPhpStep;
use Exception;

/**
 * @phpstan-type RawPhpConfig array{
 *      version: string,
 *      extensions: string[]
 * }
 */
class PhpConfig implements Config
{
    /**
     * @param RawPhpConfig $config
     * @param array $environment
     * @return void
     */
    public function __construct(protected readonly array $config, protected array $environment = [])
    {
    }

    /**
     * @return array<Step|Config>
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
     * @param RawPhpConfig[T] $config
     * @throws Exception
     */
    private function makeStep(string $name, mixed $config): Step|Config
    {
        return match ($name) {
            'version'    => new LockPhpStep($config, $this->environment),
            'extensions' => new ExtensionConfig($config, $this->environment),
            default      => throw new Exception("Unknown step: $name"),
        };
    }
}
