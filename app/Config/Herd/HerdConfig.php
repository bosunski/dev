<?php

namespace App\Config\Herd;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Step\Herd\LockPhpStep;
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
 * @phpstan-type RawHerdConfig array{
 *     sites?: array<RawSiteConfig | string>,
 *     php?: RawPhpConfig|string
 * }
 *
 * @phpstan-type RawHerdEnvironment array{
 *      bin: string,
 *      dir: string,
 *      version: string,
 *      extensionPath: string,
 *      cwd: string,
 *      home: string,
 *      pecl: string
 * }
 */
class HerdConfig implements Config
{
    /**
     * @param RawHerdConfig $config
     * @return void
     */
    public function __construct(protected readonly array $config)
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
            if ($name === 'php') {
                $steps[] = is_array($config) ? new PhpConfig($config) : new LockPhpStep($config);
            }

            if ($name === 'sites') {
                $steps[] = new Sites($config);
            }
        }

        return $steps;
    }
}
