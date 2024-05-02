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
    use Concerns\ResolvesEnvironment;

    public function __construct(protected Dev $dev)
    {
    }

    public function paths(): array
    {
        /** @var RawValetConfig $config */
        $config = $this->dev->config->up()->get(ValetPlugin::NAME) ?? [];
        if (empty($config)) {
            return [];
        }

        $environment = $this->resolveEnvironmentSettings($config);

        return [dirname($environment['bin'])];
    }
}
