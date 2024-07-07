<?php

namespace App\Plugin;

use App\Dev;
use App\IO\IOInterface;
use App\Plugin\Capability\Capability;
use App\Plugins\Brew\BrewPlugin;
use App\Plugins\Composer\ComposerPlugin;
use App\Plugins\Core\CorePlugin;
use App\Plugins\Spc\SpcPlugin;
use App\Plugins\Valet\ValetPlugin;
use Illuminate\Contracts\Container\BindingResolutionException;
use RuntimeException;
use UnexpectedValueException;

/**
 * This class is a simplified version of the PluginManager in Composer.
 * The plugin architecture is based on Composer's plugin system and as such
 * you can find similarities in this code and some other parts of the application.
 *
 * @see https://github.com/composer/composer/blob/main/src/Composer/Plugin/PluginManager.php
 */
class PluginManager
{
    /**
     * @var PluginInterface[]
     */
    protected $plugins = [];

    /**
     * @var class-string<PluginInterface>[]
     */
    protected const DEFAULT_PLUGINS = [
        CorePlugin::class,
        ValetPlugin::class,
        BrewPlugin::class,
        ComposerPlugin::class,
        SpcPlugin::class,
    ];

    public function __construct(protected readonly Dev $dev, protected readonly IOInterface $io)
    {
    }

    public function addPlugin(PluginInterface $plugin): void
    {
        $this->plugins[] = $plugin;
        $plugin->activate($this->dev);
    }

    public function loadInstalledPlugins(): void
    {
        foreach (self::DEFAULT_PLUGINS as $plugin) {
            $this->addPlugin(new $plugin());
        }
    }

    /**
     * @template T of Capability
     * @param class-string<T> $capabilityClassName
     * @param array<string, mixed> $ctorArgs
     * @return T[]
     * @throws RuntimeException
     */
    public function getPluginCapabilities(string $capabilityClassName, array $ctorArgs = []): array
    {
        $capabilities = [];
        foreach ($this->plugins as $plugin) {
            $capability = $this->getPluginCapability($plugin, $capabilityClassName, $ctorArgs);
            if ($capability !== null) {
                $capabilities[] = $capability;
            }
        }

        return $capabilities;
    }

    /**
     * @template T of Capability
     * @param PluginInterface $plugin
     * @param class-string<T> $capabilityClassName
     * @param array<string, mixed> $ctorArgs
     * @return null|T
     * @throws RuntimeException
     * @throws BindingResolutionException
     */
    public function getPluginCapability(PluginInterface $plugin, string $capabilityClassName, array $ctorArgs = []): ?Capability
    {
        if ($capabilityClass = $this->getCapabilityImplementationClassName($plugin, $capabilityClassName)) {
            if (! class_exists($capabilityClass)) {
                throw new \RuntimeException("Cannot instantiate Capability, as class $capabilityClass from plugin " . get_class($plugin) . ' does not exist.');
            }

            $ctorArgs['plugin'] = $plugin;
            $capabilityObj = app()->make($capabilityClass, $ctorArgs);

            if (! $capabilityObj instanceof Capability || ! $capabilityObj instanceof $capabilityClassName) {
                throw new \RuntimeException(
                    'Class ' . $capabilityClass . ' must implement both App\Plugin\Capability\Capability and ' . $capabilityClassName . '.'
                );
            }

            return $capabilityObj;
        }

        return null;
    }

    /**
     * @param PluginInterface $plugin
     * @param class-string<Capability> $capability
     * @throws RuntimeException On empty or non-string implementation class name value
     * @return null|string       The fully qualified class of the implementation or null if Plugin is not of Capable type or does not provide it
     */
    protected function getCapabilityImplementationClassName(PluginInterface $plugin, string $capability): ?string
    {
        if (! ($plugin instanceof Capable)) {
            return null;
        }

        $capabilities = $plugin->capabilities();
        if (! empty($capabilities[$capability]) && trim($capabilities[$capability])) {
            return trim($capabilities[$capability]);
        }

        if (
            array_key_exists($capability, $capabilities)
            && (empty($capabilities[$capability]) || ! trim($capabilities[$capability]))
        ) {
            throw new UnexpectedValueException('Plugin ' . get_class($plugin) . ' provided invalid capability class name(s), got ' . var_export($capabilities[$capability], true));
        }

        return null;
    }
}
