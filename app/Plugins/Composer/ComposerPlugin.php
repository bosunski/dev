<?php

namespace App\Plugins\Composer;

use App\Dev;
use App\Plugin\PluginInterface;

class ComposerPlugin implements PluginInterface
{
    private Dev $dev;

    public function activate(Dev $dev): void
    {
        $this->dev = $dev;
        $this->dev->config->addStepResolver(new ComposerStepResolver($this->dev));
    }

    public function deactivate(Dev $dev): void
    {
    }

    public function uninstall(Dev $dev): void
    {
    }
}
