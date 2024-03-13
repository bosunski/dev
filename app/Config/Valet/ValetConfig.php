<?php

namespace App\Config\Valet;

use App\Config\Config;
use App\Contracts\ConfigInterface;
use App\Step\StepInterface;
use App\Step\Valet\LockPhpStep;
use Exception;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ValetConfig implements ConfigInterface
{
    private const PHP_VERSION_MAP = [
        '8.3' => 'php',
        '8.2' => 'php@8.2',
        '8.1' => 'php@8.1',
        '8.0' => 'php@8.0',
        '7.4' => 'php@7.4',
    ];

    protected readonly array $config;

    public function __construct(array $config, protected Config $garmConfig)
    {
        $this->config = $this->resolveEnvironmentSettings($config);
        $this->injectEnvs();
    }

    /**
     * @throws Exception
     */
    public function steps(): array
    {
        $steps = [];
        foreach ($this->config as $name => $config) {
            if ($name === 'environment') {
                continue;
            }

            $configOrStep = $this->makeStep($name, $config);

            if ($configOrStep instanceof ConfigInterface) {
                $steps = [...$steps, ...$configOrStep->steps()];

                continue;
            }

            $steps[] = $configOrStep;
        }

        return $steps;
    }

    /**
     * @throws Exception
     */
    private function makeStep(string $name, mixed $config): StepInterface|ConfigInterface
    {
        return match ($name) {
            'sites' => new Sites($config),
            'php'   => is_array($config) ? new PhpConfig($config, $this->config['environment']['php']) : new LockPhpStep($config, $this->config['environment']['php']),
            default => throw new Exception("Unknown step: $name"),
        };
    }

    private function resolveEnvironmentSettings(array $config): array
    {
        $environment = ['php' => []];
        $configVersion = (string) Arr::get($config, 'php.version', Arr::get($config, 'php', ''));
        $bin = self::phpPath($configVersion);

        $environment['php']['bin'] = $bin ?: trim(`which php` ?? '');
        $environment['php']['pecl'] = dirname($environment['php']['bin']) . '/pecl';
        $environment['php']['dir'] = dirname($environment['php']['bin'], 2);
        $environment['php']['extensionPath'] = $this->currentPhpExtensionPath($environment['php']['bin']);
        $environment['php']['version'] = self::PHP_VERSION_MAP[$configVersion] ?? $configVersion;
        $environment['php']['cwd'] = $this->garmConfig->cwd();

        $config['environment'] = $environment;

        return $config;
    }

    private function injectEnvs(): void
    {
        $this->garmConfig->environment->put('PHP_DIR', $this->config['environment']['php']['dir']);
        $this->garmConfig->environment->put('PHP_BIN', $this->config['environment']['php']['bin']);

        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null;
        $this->garmConfig->environment->put('HERD_OR_VALET', 'valet');
        $this->garmConfig->environment->put('SITE_PATH', "$home/.config/valet/Nginx");
        $this->garmConfig->environment->put('VALET_OR_HERD_SITE_PATH', "$home/.config/valet/Nginx");
    }

    public static function phpPath(string $version): string
    {
        $source = self::PHP_VERSION_MAP[$version] ?? null;
        if (! $source) {
            return '';
        }

        $command = "find /opt/homebrew/Cellar/$source | grep \"$source/$version.*/bin/php$\"";
        $output = Str::of(self::runCommand($command)->output());
        $paths = $output->explode(PHP_EOL)->filter();

        if ($paths->isEmpty()) {
            return '';
        }

        $versions = collect();
        foreach ($paths->filter() as $path) {
            preg_match("/$version\.\d+/", $path, $matches);
            $versions[$matches[0]] = $path;
        }

        $latest = $versions->keys()->first();
        $versions->each(function ($path, $version) use (&$latest): void {
            if (version_compare($version, $latest, '>')) {
                $latest = $version;
            }
        });

        return $versions->get($latest);
    }

    private function currentPhpExtensionPath(?string $phpBin = null): string
    {
        $phpBin ??= self::runCommand('which php')->output();

        try {
            return self::runCommand("$phpBin -nr \"echo ini_get('extension_dir');\"")->output();
        } catch (ProcessFailedException $exception) {
            // TODO: Handle this better
            echo $exception->getMessage();

            throw $exception;
        }
    }

    private static function runCommand(string $command): ProcessResult
    {
        return Process::timeout(3)->command($command)->run()->throw();
    }
}
