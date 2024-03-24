<?php

namespace App\Plugins\Brew;

use App\Dev;
use App\Plugin\PluginInterface;

class BrewPlugin implements PluginInterface
{
    private Dev $dev;

    public function activate(Dev $dev): void
    {
        $this->dev = $dev;
        $this->dev->config->addStepResolver(new BrewStepResolver($this->dev));
    }

    public function deactivate(Dev $dev): void
    {
    }

    public function uninstall(Dev $dev): void
    {
    }
}
