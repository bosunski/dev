<?php

namespace App\Plugins\Valet\Concerns;

use App\Plugins\Valet\Config\ValetConfig;
use App\Plugins\Valet\ValetStepResolver;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

use function Illuminate\Filesystem\join_paths;

/**
 * @phpstan-import-type RawValetEnvironment from ValetConfig
 * @phpstan-import-type RawValetConfig from ValetConfig
 */
trait ResolvesEnvironment
{
    /**
     * @param RawValetConfig $config
     * @return RawValetEnvironment
     * @throws ProcessFailedException
     * @throws ProcessTimedOutException
     * @throws RuntimeException
     */
    private function resolveEnvironmentSettings(array $config): array
    {
        $configVersion = '8.3';
        if (isset($config['php'])) {
            $configVersion = is_array($config['php'])
                ? $config['php']['version']
                : $config['php'];
        }

        $bin = self::phpPath($configVersion) ?: trim(`which php` ?? '');

        return [
            'bin'           => $bin ?: trim(`which php` ?? ''),
            'pecl'          => dirname($bin) . '/pecl',
            'dir'           => dirname($bin, 2),
            'extensionPath' => $this->currentPhpExtensionPath($bin),
            'version'       => ValetStepResolver::PHP_VERSION_MAP[$configVersion] ?? $configVersion,
            'cwd'           => $this->dev->config->cwd(),
            'home'          => $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null,
            'composer'      => $composer = $this->composerBinPath(),
            'valet'         => [
                'bin'           => $this->valetBinPath($composer),
                'nginxPath'     => join_paths($_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '', '.config/valet/Nginx'),
                'path'          => join_paths($_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '', '.config/valet'),
            ],
        ];
    }

    protected static function phpPath(string $version): string
    {
        $source = ValetStepResolver::PHP_VERSION_MAP[$version] ?? null;
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
        if (! is_string($latest)) {
            // The PHP version is not found and probaly because the version is not installed
            // in this case, there should be a step to install the PHP version before we get here
            return '';
        }

        $versions->each(function (string $path, string $version) use (&$latest): void {
            if (version_compare($version, $latest, '>')) {
                $latest = $version;
            }
        });

        return $versions->get($latest);
    }

    protected function valetBinPath(string $composer): string
    {
        $composerHome = trim(self::runCommand("$composer global config home")->output());

        return $composerHome . '/vendor/bin/valet';
    }

    protected function composerBinPath(): string
    {
        $valetPath = `which composer`;
        if ($valetPath) {
            return trim($valetPath);
        }

        return 'composer';
    }

    protected function currentPhpExtensionPath(?string $phpBin = null): string
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

    protected static function runCommand(string $command): ProcessResult
    {
        return Process::timeout(3)->command($command)->run()->throw();
    }
}
