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
            if ($name === 'version') {
                $steps[] = new LockPhpStep($config, $this->environment);
                continue;
            }

            if ($name === 'extensions') {
                $steps[] = new ExtensionConfig($config, $this->environment);
                continue;
            }
        }

        return $steps;
    }
}
