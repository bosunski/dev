<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Capability\EnvProvider;
use App\Plugins\Valet\Config\ValetConfig;

use function Illuminate\Filesystem\join_paths;

/**
 * @phpstan-import-type RawValetConfig from ValetConfig
 */
class ValetEnvProvider implements EnvProvider
{
    public function __construct(protected Dev $dev, protected ValetPlugin $plugin)
    {
    }

    public function envs(): array
    {
        $environment = $this->plugin->environment($this->dev->config);
        $iniScanDir = $this->dev->config->devPath('php.d');

        return [
            'PHP_DIR'                 => $environment['dir'],
            'PHP_BIN'                 => $environment['bin'],
            'HERD_OR_VALET'           => 'valet',
            'SITE_PATH'               => $sitesPath = join_paths($environment['valet']['path'], 'Nginx'),
            'VALET_OR_HERD_SITE_PATH' => $sitesPath,
            'PHP_INI_SCAN_DIR'        => $iniScanDir,
        ];
    }
}
