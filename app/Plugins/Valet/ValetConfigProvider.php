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
        if (! $this->plugin->active($this->dev->config)) {
            return [];
        }

        return [
            'valet' => new ValetStepResolver(
                $this->dev,
                fn () => $this->plugin->environment($this->dev->config),
            ),
        ];
    }
}
