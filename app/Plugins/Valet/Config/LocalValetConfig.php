<?php

namespace App\Plugins\Valet\Config;

use App\Config\Config;
use App\Exceptions\UserException;
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
        $valetDir = $this->resolveValetDir($this->devConfig);
        $this->config = [
            'dir'     => $valetDir,
            'bin'     => $this->devConfig->home('.config/composer/vendor/bin/valet'),
            'version' => '4.0.0',
            'path'    => $valetDir,
            'tld'     => 'test',
            'php'     => $bin = $this->devConfig->path('bin/php'),
            'php.dir' => dirname($bin),
        ];
    }

    private function resolveValetDir(Config $config): string
    {
        return match(true) {
            $config->isDarwin() => $config->home('.config/valet'),
            $config->isLinux()  => $config->home('.valet'),
            default             => throw new UserException('Valet is not supported on this platform: ' . $config->platform()),
        };
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

    public function json(): array
    {
        $content = @file_get_contents($this->config['dir'] . '/config.json');
        if (! $content) {
            return [];
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
