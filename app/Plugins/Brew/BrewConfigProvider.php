<?php

namespace App\Plugins\Brew;

use App\Dev;
use App\Plugin\Capability\ConfigProvider;

class BrewConfigProvider implements ConfigProvider
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
            new BrewStepResolver($this->dev),
        ];
    }
}
