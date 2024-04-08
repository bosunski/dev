<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Capability\ConfigProvider;
use App\Plugin\Capability\EnvProvider;
use App\Plugin\Capability\PathProvider;
use App\Plugin\Capable;
use App\Plugin\PluginInterface;
use Illuminate\Support\Facades\File;

class ValetPlugin implements Capable, PluginInterface
{
    public const NAME = 'valet';

    public function activate(Dev $dev): void
    {
        if (! is_dir($path = $dev->config->devPath('php.d'))) {
            mkdir($path, recursive: true);
        }
    }

    public function deactivate(Dev $dev): void
    {
    }

    public function uninstall(Dev $dev): void
    {
        if (is_dir($path = $dev->config->devPath('php.d'))) {
            File::deleteDirectory($path);
        }
    }

    public function capabilities(): array
    {
        return [
            ConfigProvider::class => ValetConfigProvider::class,
            EnvProvider::class    => ValetEnvProvider::class,
            PathProvider::class   => ValetPathProvider::class,
        ];
    }
}
