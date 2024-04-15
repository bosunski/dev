<?php

namespace App\Plugins\Spc\Config;

use App\Contracts\ConfigInterface;
use App\Plugins\Spc\Steps\ExtensionInstallStep;

class ExtensionConfig implements ConfigInterface
{
    /**
     * @param string[] $extensions
     * @param array $environment
     * @return void
     */
    public function __construct(protected readonly array $extensions, protected array $environment)
    {
    }

    public function steps(): array
    {
        foreach ($this->extensions as $name => $config) {
            $steps[] = new ExtensionInstallStep($name, $this->environment, $config ?? []);
        }

        return $steps ?? [];
    }
}
