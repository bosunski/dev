<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\PluginInterface;

class ValetPlugin implements PluginInterface
{
    private Dev $dev;

    public function activate(Dev $dev): void
    {
        $this->dev = $dev;
        $this->dev->config->addStepResolver(new ValetStepResolver($this->dev));
    }

    public function deactivate(Dev $dev): void
    {
    }

    public function uninstall(Dev $dev): void
    {
    }
}
