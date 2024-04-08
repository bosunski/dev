<?php

namespace App\Plugins\Composer;

use App\Dev;
use App\Plugin\Capability\ConfigProvider;

class ComposerConfigProvider implements ConfigProvider
{
    public function __construct(protected Dev $dev)
    {
    }

    public function steps(): array
    {
        return [];
    }

    public function validate(): bool
    {
        return true;
    }

    public function stepResolvers(): array
    {
        return [
            'composer' => new ComposerStepResolver(),
        ];
    }
}
