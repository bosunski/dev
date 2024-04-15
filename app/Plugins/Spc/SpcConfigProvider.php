<?php

namespace App\Plugins\Spc;

use App\Dev;
use App\Plugin\Capability\ConfigProvider;

class SpcConfigProvider implements ConfigProvider
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
            'spc' => new SpcStepResolver($this->dev),
        ];
    }
}
