<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Contracts\Config;
use App\Plugin\StepResolverInterface;
use App\Plugins\Valet\Config\ValetConfig;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ValetStepResolver implements StepResolverInterface
{
    public const PHP_VERSION_MAP = [
        '8.3' => 'php',
        '8.2' => 'php@8.2',
        '8.1' => 'php@8.1',
        '8.0' => 'php@8.0',
        '7.4' => 'php@7.4',
    ];

    protected array $config = [];

    public function __construct(protected readonly Dev $dev)
    {
    }

    public function name(): string
    {
        return 'valet';
    }

    /**
     * @param mixed $args
     * @return Config|Step]
     */
    public function resolve(mixed $args): Config | array
    {
        $this->config = $this->resolveEnvironmentSettings($args);
        $this->injectEnvs();

        return new ValetConfig($args, $this->config['environment']);
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
        $environment['php']['cwd'] = $this->dev->config->cwd();

        $config['environment'] = $environment;

        return $config;
    }

    private function injectEnvs(): void
    {
        $this->dev->config->environment->put('PHP_DIR', $this->config['environment']['php']['dir']);
        $this->dev->config->environment->put('PHP_BIN', $this->config['environment']['php']['bin']);

        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null;
        $this->dev->config->environment->put('HERD_OR_VALET', 'valet');
        $this->dev->config->environment->put('SITE_PATH', "$home/.config/valet/Nginx");
        $this->dev->config->environment->put('VALET_OR_HERD_SITE_PATH', "$home/.config/valet/Nginx");

        $this->dev->config->paths->push(dirname($this->config['environment']['php']['bin']));
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
