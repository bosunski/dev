<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Capability\EnvProvider;
use Illuminate\Support\Str;

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
        $linkPath = $this->plugin->env()->get('php');

        // We need to find a way to ensure envs are loaded properly so that we can be sure
        // that all the env injection are complete before we use them
        return [
            'PHP_BIN'                 => $linkPath,
            'PHP_DIR'                 => Str::before($linkPath, '/bin/php'),
            'HERD_OR_VALET'           => $bin = $this->plugin->env()->get('bin'),
            'VALET_BIN'               => $bin,
            'VALET_PATH'              => $this->plugin->env()->get('path'),
            'SITE_PATH'               => $sitesPath = join_paths($this->plugin->env()->get('dir'), 'Nginx'),
            'VALET_OR_HERD_SITE_PATH' => $sitesPath,
            'PHP_INI_SCAN_DIR'        => $iniScanDir,
        ];
    }
}
