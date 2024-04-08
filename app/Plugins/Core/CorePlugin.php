<?php

namespace App\Plugins\Core;

use App\Dev;
use App\Plugin\Capability\CommandProvider;
use App\Plugin\Capability\ConfigProvider;
use App\Plugin\Capable;
use App\Plugin\PluginInterface;

class CorePlugin implements Capable, PluginInterface
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
            ConfigProvider::class  => CoreConfigProvider::class,
            CommandProvider::class => CoreCommandProvider::class,
        ];
    }
}
