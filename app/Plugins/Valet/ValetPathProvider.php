<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Capability\PathProvider;

class ValetPathProvider implements PathProvider
{
    use Concerns\ResolvesEnvironment;

    public function __construct(protected Dev $dev)
    {
    }

    public function paths(): array
    {
        $config = $this->dev->config->up()->get(ValetPlugin::NAME) ?? [];
        if (empty($config)) {
            return [];
        }

        $environment = $this->resolveEnvironmentSettings($config);

        return [
            dirname($environment['php']['bin']),
        ];
    }
}
