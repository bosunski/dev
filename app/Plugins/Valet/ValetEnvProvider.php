<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Capability\EnvProvider;

use function Illuminate\Filesystem\join_paths;

class ValetEnvProvider implements EnvProvider
{
    public function __construct(protected Dev $dev, protected ValetPlugin $plugin)
    {
    }

    public function envs(): array
    {
        if (! $this->plugin->active($this->dev->config)) {
            return [];
        }

        $iniScanDir = $this->dev->config->devPath('php.d');

        return [
            'PHP_BIN'                 => $phpBin = $this->plugin->env()->get('php'),
            'PHP_DIR'                 => dirname($phpBin),
            'HERD_OR_VALET'           => 'valet',
            'SITE_PATH'               => $sitesPath = join_paths($this->plugin->env()->get('dir'), 'Nginx'),
            'VALET_OR_HERD_SITE_PATH' => $sitesPath,
            'PHP_INI_SCAN_DIR'        => $iniScanDir,
        ];
    }
}
