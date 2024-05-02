<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Capability\EnvProvider;
use App\Plugins\Valet\Config\ValetConfig;

/**
 * @phpstan-import-type RawValetConfig from ValetConfig
 */
class ValetEnvProvider implements EnvProvider
{
    use Concerns\ResolvesEnvironment;

    public function __construct(protected Dev $dev)
    {
    }

    public function envs(): array
    {
        /** @var RawValetConfig $config */
        $config = $this->dev->config->up()->get(ValetPlugin::NAME) ?? [];
        $environment = $this->resolveEnvironmentSettings($config);

        $home = $environment['home'];
        $iniScanDir = $this->dev->config->devPath('php.d');

        return [
            'PHP_DIR'                 => $environment['dir'],
            'PHP_BIN'                 => $environment['bin'],
            'HERD_OR_VALET'           => 'valet',
            'SITE_PATH'               => "$home/.config/valet/Nginx",
            'VALET_OR_HERD_SITE_PATH' => "$home/.config/valet/Nginx",
            'PHP_INI_SCAN_DIR'        => $iniScanDir,
        ];
    }
}
