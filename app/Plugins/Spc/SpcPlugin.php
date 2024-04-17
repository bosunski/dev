<?php

namespace App\Plugins\Spc;

use App\Dev;
use App\Plugin\Capability\CommandProvider;
use App\Plugin\Capability\ConfigProvider;
use App\Plugin\Capable;
use App\Plugin\PluginInterface;
use Illuminate\Support\Facades\File;

class SpcPlugin implements Capable, PluginInterface
{
    public function activate(Dev $dev): void
    {
        if (! is_dir($path = $dev->config->globalPath('spc'))) {
            mkdir($path, recursive: true);
        }
    }

    public function deactivate(Dev $dev): void
    {
    }

    public function uninstall(Dev $dev): void
    {
        if (is_dir($path = $dev->config->globalPath('spc'))) {
            File::deleteDirectory($path);
        }
    }

    public function capabilities(): array
    {
        return [
            ConfigProvider::class  => SpcConfigProvider::class,
            CommandProvider::class => SpcCommandProvider::class,
        ];
    }
}
