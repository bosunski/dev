<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Capability\ConfigProvider;
use App\Plugin\Capability\EnvProvider;
use App\Plugin\Capability\PathProvider;
use App\Plugin\Capable;
use App\Plugin\PluginInterface;

class ValetPlugin implements Capable, PluginInterface
{
    public const NAME = 'valet';

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
            ConfigProvider::class => ValetConfigProvider::class,
            EnvProvider::class    => ValetEnvProvider::class,
            PathProvider::class   => ValetPathProvider::class,
        ];
    }
}
