<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Capability\PathProvider;
use App\Plugins\Valet\Config\ValetConfig;

/**
 * @phpstan-import-type RawValetConfig from ValetConfig
 */
class ValetPathProvider implements PathProvider
{
    public function __construct(protected Dev $dev, protected ValetPlugin $plugin)
    {
    }

    public function paths(): array
    {
        /** @var RawValetConfig $config */
        $config = $this->dev->config->up()->get(ValetPlugin::NAME) ?? [];
        if (empty($config)) {
            return [];
        }

        return [dirname($this->plugin->env()->get('bin'))];
    }
}
