<?php

namespace App\Plugins\Valet\Config;

use App\Contracts\ConfigInterface;
use App\Plugins\Valet\Steps\ExtensionInstallStep;

class ExtensionConfig implements ConfigInterface
{
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
