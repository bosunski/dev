<?php

namespace App\Config\Php;

use App\Config\Herd\HerdConfig;
use App\Plugin\Contracts\Config;
use App\Step\Php\ExtensionInstallStep;

/**
 * @phpstan-import-type RawHerdEnvironment from HerdConfig
 * @phpstan-import-type RawPhpConfig from HerdConfig
*/
class ExtensionConfig implements Config
{
    /**
     * @param RawPhpConfig['extensions'] $extensions
     * @param RawHerdEnvironment $environment
     * @return void
     */
    public function __construct(protected readonly array $extensions, protected array $environment)
    {
    }

    public function steps(): array
    {
        foreach ($this->extensions as $name => $config) {
            $steps[] = new ExtensionInstallStep($name, $this->environment, $config);
        }

        return $steps ?? [];
    }
}
