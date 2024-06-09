<?php

namespace App;

use App\Config\Config;
use App\Contracts\EnvResolver;
use App\Execution\Runner;
use App\IO\IOInterface;
use App\Plugin\Capability\EnvProvider;
use App\Plugin\Capability\PathProvider;
use App\Plugin\PluginManager;
use Illuminate\Support\Collection;
use RuntimeException;

class Dev implements EnvResolver
{
    protected PluginManager $pluginManager;

    public readonly string $name;

    public function __construct(public readonly Config $config, public readonly Runner $runner, protected IOInterface $io)
    {
        $this->name = 'dev';
        $this->runner->setEnvResolver($this);
    }

    public function getPluginManager(): PluginManager
    {
        return $this->pluginManager;
    }

    public function setPluginManager(PluginManager $pluginManager): void
    {
        $this->pluginManager = $pluginManager;
    }

    public function io(): IOInterface
    {
        return $this->io;
    }

    public function setIO(IOInterface $io): void
    {
        $this->io = $io;
    }

    public function envs(): Collection
    {
        $envs = [];
        foreach ($this->pluginManager->getPluginCapabilities(EnvProvider::class, ['dev' => $this]) as $capability) {
            $newEnvs = $capability->envs();
            $envs = array_merge($envs, $newEnvs);
        }

        return $this->config->envs()->merge($envs)->merge([
            'DEV_PATH'         => $this->config->devPath(),
            'DEV'              => '1',
            'DEV_SOURCE_ROOT'  => Config::sourcePath(),
            'DEV_PROJECT_ROOT' => $this->config->projectPath(),
        ]);
    }

    /**
     * @return string[]
     * @throws RuntimeException
     */
    public function paths(): array
    {
        $paths = [
            $this->config->devPath('bin'),
            $this->config->globalPath('bin'),
        ];

        foreach ($this->pluginManager->getPluginCapabilities(PathProvider::class, ['dev' => $this]) as $capability) {
            $newPaths = $capability->paths();
            $paths = array_merge($paths, $newPaths);
        }

        return $paths;
    }

    public function isDebug(): bool
    {
        return $this->config->isDebug();
    }

    public function initialized(): bool
    {
        return is_file($this->config->cwd('dev.yml'));
    }
}
