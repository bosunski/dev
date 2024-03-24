<?php

namespace App\Plugins\Valet;

use App\Plugin\Capability\ConfigProvider;

class ValetConfigProvider implements ConfigProvider
{
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
        return [new ValetStepResolver()];
    }
}
