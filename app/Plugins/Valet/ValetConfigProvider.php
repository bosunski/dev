<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Capability\ConfigProvider;

class ValetConfigProvider implements ConfigProvider
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
            new ValetStepResolver($this->dev),
        ];
    }
}
