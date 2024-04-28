<?php

namespace App\Plugins\Valet\Config;

use App\Contracts\ConfigInterface;
use App\Plugins\Valet\Steps\ExtensionInstallStep;

/**
 * @phpstan-import-type RawValetEnvironment from ValetConfig
 * @phpstan-import-type RawPhpConfig from ValetConfig
*/
class ExtensionConfig implements ConfigInterface
{
    /**
     * @param RawPhpConfig['extensions'] $extensions
     * @param RawValetEnvironment $environment
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
