<?php

namespace App\Plugin;

use App\Dev;

interface PluginInterface
{
    /**
     * @var string
     */
    public const PLUGIN_API_VERSION = '0.0.0';

    /**
     * Apply plugin modifications to Composer
     *
     * @return void
     */
    public function activate(Dev $dev): void;

    /**
     * Remove any hooks from Composer
     *
     * This will be called when a plugin is deactivated before being
     * uninstalled, but also before it gets upgraded to a new version
     * so the old one can be deactivated and the new one activated.
     *
     * @return void
     */
    public function deactivate(Dev $dev): void;

    /**
     * Prepare the plugin to be uninstalled
     *
     * This will be called after deactivate.
     *
     * @return void
     */
    public function uninstall(Dev $dev): void;
}
