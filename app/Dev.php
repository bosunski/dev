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

    public readonly bool $initialized;

    public function __construct(public readonly Config $config, public readonly Runner $runner, protected IOInterface $io)
    {
        $this->name = 'dev';
        $this->runner->setEnvResolver($this);

        $this->initialized = is_file($this->config->file());
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
            $paths = array_merge($paths, $capability->paths());
        }

        return $paths;
    }

    public function updateEnvironment(): bool
    {
        if (! $this->init($this->config)) {
            return false;
        }

        if ($this->createDefaultLispFile($this->config) && $this->createGitIgnoreFile($this->config)) {
            /**
             * we only want to use ShadowEnv in the runner when it's setup. At this
             * point, that is not the case since we are still setting it up.
             */
            return $this->runner->withoutShadowEnv()->exec([$this->config->brewPath('bin/shadowenv'), 'trust']);
        }

        return false;
    }

    private function init(Config $config): bool
    {
        if (is_dir($config->cwd($this->path()))) {
            return true;
        }

        return @mkdir($config->cwd($this->path()), 0755, true);
    }

    private function createDefaultLispFile(Config $config): bool
    {
        return (bool) file_put_contents($config->cwd($this->path('000_default.lisp')), $this->defaultContent($config));
    }

    private function createGitIgnoreFile(Config $config): bool
    {
        return (bool) file_put_contents($config->cwd($this->path('.gitignore')), $this->gitIgnoreContent());
    }

    private function path(?string $path = null): string
    {
        if ($path) {
            return ".shadowenv.d/$path";
        }

        return '.shadowenv.d';
    }

    private function defaultContent(Config $config): string
    {
        return view('shadowenv.default', [
            'paths' => $this->paths(),
            'envs'  => $this->envs(),
        ])->render();
    }

    private function gitIgnoreContent(): string
    {
        return <<<'EOF'
.*
!.gitignore
EOF;
    }

    public function isDebug(): bool
    {
        return $this->config->isDebug();
    }

    public function initialized(): bool
    {
        return is_file($this->config->file());
    }
}
