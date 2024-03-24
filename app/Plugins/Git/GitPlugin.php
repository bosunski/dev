<?php

namespace App\Plugins\Git;

use App\Dev;
use App\Plugin\Capability\Capabilities;
use App\Plugin\Capable;
use App\Plugin\PluginInterface;

class GitPlugin implements Capable, PluginInterface
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
            Capabilities::Command->value => GitCommandProvider::class,
        ];
    }
}
