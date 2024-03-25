<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Capability\EnvProvider;

class ValetEnvProvider implements EnvProvider
{
    use Concerns\ResolvesEnvironment;

    public function __construct(protected Dev $dev)
    {
    }

    public function envs(): array
    {
        $config = $this->dev->config->up()->get(ValetPlugin::NAME) ?? [];
        $environment = $this->resolveEnvironmentSettings($config);

        $home = $environment['php']['home'];

        return [
            'PHP_DIR'                 => $environment['php']['dir'],
            'PHP_BIN'                 => $environment['php']['bin'],
            'HERD_OR_VALET'           => 'valet',
            'SITE_PATH'               => "$home/.config/valet/Nginx",
            'VALET_OR_HERD_SITE_PATH' => "$home/.config/valet/Nginx",
        ];
    }
}