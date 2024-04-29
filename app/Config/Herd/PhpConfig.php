<?php

namespace App\Config\Herd;

use App\Config\Php\ExtensionConfig;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Step\Herd\LockPhpStep;
use Exception;

/**
 * @phpstan-import-type RawPhpConfig from HerdConfig
 */
class PhpConfig implements Config
{
    /**
     * @param RawPhpConfig $config
     *
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
            if ($name === 'extensions') {
                // @phpstan-ignore-next-line
                $steps[] = new ExtensionConfig($config, []);
            }

            if ($name === 'version') {
                $steps[] = new LockPhpStep($config);
            }
        }

        return $steps;
    }
}
