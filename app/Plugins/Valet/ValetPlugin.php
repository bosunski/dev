<?php

namespace App\Plugins\Valet;

use App\Config\Config;
use App\Dev;
use App\Exceptions\UserException;
use App\Plugin\Capability\ConfigProvider;
use App\Plugin\Capability\EnvProvider;
use App\Plugin\Capability\PathProvider;
use App\Plugin\Capable;
use App\Plugin\PluginInterface;
use App\Plugins\Valet\Config\LocalValetConfig;
use App\Plugins\Valet\Config\ValetConfig;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type RawValetEnvironment from ValetConfig
 * @phpstan-import-type RawValetConfig from ValetConfig
 */
class ValetPlugin implements Capable, PluginInterface
{
    public const NAME = 'valet';

    private ?LocalValetConfig $env = null;

    public function env(): LocalValetConfig
    {
        assert($this->env instanceof LocalValetConfig);

        return $this->env;
    }

    public function activate(Dev $dev): void
    {
        if (! $dev->initialized) {
            return;
        }

        if (! is_dir($path = $dev->config->devPath('php.d'))) {
            mkdir($path, recursive: true);
        }

        $this->env = new LocalValetConfig($dev->config);
    }

    public function deactivate(Dev $dev): void
    {
    }

    public function uninstall(Dev $dev): void
    {
        if (is_dir($path = $dev->config->devPath('php.d'))) {
            File::deleteDirectory($path);
        }
    }

    public function capabilities(): array
    {
        return [
            ConfigProvider::class => ValetConfigProvider::class,
            EnvProvider::class    => ValetEnvProvider::class,
            PathProvider::class   => ValetPathProvider::class,
        ];
    }

    public function active(Config $devConfig): bool
    {
        return ! empty($devConfig->up()->get(ValetPlugin::NAME) ?? []);
    }
}
