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
        foreach ($this->pluginManager->getCcs(EnvProvider::class, [$this]) as $capability) {
            $newEnvs = $capability->envs();
            $envs = array_merge($envs, $newEnvs);
        }

        return $this->config->envs()->merge($envs)->merge([
            'DEV_PATH'     => $this->config->devPath(),
            'DEV'          => 1,
            'SOURCE_ROOT'  => Config::sourcePath(),
            'SERVICE_ROOT' => $this->config->servicePath(),
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
        ];

        foreach ($this->pluginManager->getCcs(PathProvider::class, [$this]) as $capability) {
            $newPaths = $capability->paths();
            $paths = array_merge($paths, $newPaths);
        }

        return $paths;
    }
}
