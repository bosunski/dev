<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Capability\ConfigProvider;

class ValetConfigProvider implements ConfigProvider
{
    public function __construct(protected Dev $dev, protected ValetPlugin $plugin)
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
            'valet' => new ValetStepResolver(
                $this->dev,
                $this->plugin->environment($this->dev->config),
            ),
        ];
    }
}
