<?php

namespace App\Config\Php;

use App\Contracts\ConfigInterface;
use App\Step\Php\ExtensionInstallStep;

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
