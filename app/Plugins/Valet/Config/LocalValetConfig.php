<?php

namespace App\Plugins\Valet\Config;

use App\Config\Config;
use RuntimeException;

/**
 * @phpstan-type RawLocalValetConfig array{
 *  dir: string,
 *  bin: string,
 *  version: string,
 *  path: string,
 *  tld: string,
 *  php: string,
 * }
 */
class LocalValetConfig
{
    /** @var RawLocalValetConfig */
    private array $config;

    public function __construct(protected Config $devConfig)
    {
        $this->config = [
            'dir'     => $this->devConfig->home('.config/valet'),
            'bin'     => $this->devConfig->path('bin/valet'),
            'version' => '4.0.0',
            'path'    => $this->devConfig->home('.config/valet'),
            'tld'     => 'test',
            'php'     => $this->devConfig->path('bin/php'),
        ];
    }

    /**
     * @template T of key-of<RawLocalValetConfig>
     * @param T $key
     * @return RawLocalValetConfig[T]
     */
    public function get(string $key): mixed
    {
        if (! array_key_exists($key, $this->config)) {
            throw new RuntimeException("Missing valet config key: $key");
        }

        return $this->config[$key];
    }

    /**
     * @template T of key-of<RawLocalValetConfig>
     * @param T $key
     * @param RawLocalValetConfig[T] $value
     * @return void
     */
    public function put(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }
}
