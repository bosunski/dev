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

    protected static function phpPath(string $version): string
    {
        $source = ValetStepResolver::PHP_VERSION_MAP[$version] ?? null;
        if (! $source) {
            throw new UserException("Unknown PHP version '$version' in configuration.", 'Supported versions: ' . implode(', ', array_keys(ValetStepResolver::PHP_VERSION_MAP)));
        }

        /**
         * ToDo: Don't limit the search to /opt/homebrew/Cellar. Maybe allow SPC binaries?
         * Counterpoint: Valet actually uses homebrew to install PHP, so it's safe to assume
         * that the PHP binaries are in /opt/homebrew/Cellar.
         */
        $command = "find /opt/homebrew/Cellar/$source | grep \"$source/$version.*/bin/php$\"";
        $output = Str::of(self::runCommand($command)->output());
        $paths = $output->explode(PHP_EOL)->filter();

        if ($paths->isEmpty()) {
            throw new UserException("Valet: PHP $version is not installed in /opt/homebrew/Cellar/$source.");
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
            throw new UserException("Valet: PHP $version is not installed in /opt/homebrew/Cellar/$source.");
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
        $valetPath = `command -v composer`;
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
        // This doesn't have Shadowenv covrage and might not work as expected
        // especially when an environment specific binary is being used
        return Process::command($command)->run()->throw();
    }
}
