<?php

namespace App\Plugins\Brew;

use App\Dev;
use App\Plugin\Capability\ConfigProvider;
use App\Plugin\Capable;
use App\Plugin\PluginInterface;

class BrewPlugin implements Capable, PluginInterface
{
    private Dev $dev;

    public function activate(Dev $dev): void
    {
        $this->dev = $dev;
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
            ConfigProvider::class => BrewConfigProvider::class,
        ];
    }
}
