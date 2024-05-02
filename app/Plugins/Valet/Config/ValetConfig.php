<?php

namespace App\Plugins\Valet\Config;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Steps\LockPhpStep;
use Exception;

/**
 * @phpstan-type RawExtensionConfig array{
 *     before?: string,
 *     options?: array<string, string>
 * }
 *
 * @phpstan-type RawPhpConfig array{
 *    version: string,
 *    extensions?: array<string, RawExtensionConfig | true>
 * }
 *
 * @phpstan-type RawSiteConfig array{
 *      proxy?: string,
 *      secure?: bool,
 *      host: string
 * }
 *
 * @phpstan-type RawValetConfig array{
 *     sites?: array<RawSiteConfig | string>,
 *     php?: RawPhpConfig|string
 * }
 *
 * @phpstan-type RawValetEnvironment array{
 *      bin: string,
 *      dir: string,
 *      version: string,
 *      extensionPath: string,
 *      cwd: string,
 *      home: string,
 *      pecl: string
 * }
 */
class ValetConfig implements Config
{
    /**
     * @param RawValetConfig $config
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
        if (isset($this->config['php'])) {
            $config = $this->config['php'];
            $steps[] = is_array($config) ? new PhpConfig($config, $this->environment) : new LockPhpStep($config, $this->environment);
        }

        if (isset($this->config['sites'])) {
            $steps[] = new Sites($this->config['sites']);
        }

        return $steps;
    }
}
