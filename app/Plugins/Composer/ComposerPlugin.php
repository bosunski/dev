<?php

namespace App\Plugins\Composer;

use App\Dev;
use App\Plugin\Capability\ConfigProvider;
use App\Plugin\Capability\PathProvider;
use App\Plugin\Capable;
use App\Plugin\PluginInterface;

class ComposerPlugin implements Capable, PluginInterface
{
    public function activate(Dev $dev): void
    {
    }

    public function deactivate(Dev $dev): void
    {
    }

    public function uninstall(Dev $dev): void
    {
    }

    public function capabilities(): array
    {
        return [
            ConfigProvider::class => ComposerConfigProvider::class,
            PathProvider::class   => ComposerPathProvider::class,
        ];
    }
}
