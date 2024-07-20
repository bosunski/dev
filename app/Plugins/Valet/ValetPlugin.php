<?php

namespace App\Plugins\Valet;

use App\Config\Config;
use App\Dev;
use App\Plugin\Capability\ConfigProvider;
use App\Plugin\Capability\EnvProvider;
use App\Plugin\Capability\PathProvider;
use App\Plugin\Capable;
use App\Plugin\PluginInterface;
use App\Plugins\Valet\Config\ValetConfig;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

use function Illuminate\Filesystem\join_paths;

/**
 * @phpstan-import-type RawValetEnvironment from ValetConfig
 * @phpstan-import-type RawValetConfig from ValetConfig
 */
class ValetPlugin implements Capable, PluginInterface
{
    public const NAME = 'valet';

    /**
     * @var RawValetEnvironment|array{}
     */
    protected array $environment = [];

    public function activate(Dev $dev): void
    {
        if (! $dev->initialized) {
            return;
        }

        if (! is_dir($path = $dev->config->devPath('php.d'))) {
            mkdir($path, recursive: true);
        }
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

    /**
     * @return RawValetEnvironment
     * @throws ProcessFailedException
     * @throws ProcessTimedOutException
     */
    public function environment(Config $devConfig): array
    {
        if (! empty($this->environment)) {
            return $this->environment;
        }

        /** @var RawValetConfig $config */
        $config = $devConfig->up()->get(ValetPlugin::NAME) ?? [];

        $configVersion = '8.3';
        if (isset($config['php'])) {
            $configVersion = is_array($config['php'])
                ? $config['php']['version']
                : $config['php'];
        }

        $phpBin = self::phpPath($configVersion) ?: trim(`which php` ?? '');

        return $this->environment = [
            'bin'           => $phpBin ?: trim(`which php` ?? ''),
            'pecl'          => dirname($phpBin) . '/pecl',
            'dir'           => dirname($phpBin, 2),
            'extensionPath' => $this->currentPhpExtensionPath($phpBin),
            'version'       => ValetStepResolver::PHP_VERSION_MAP[$configVersion] ?? $configVersion,
            'cwd'           => $devConfig->cwd(),
            'home'          => $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null,
            'composer'      => $composer = $this->composerBinPath(),
            'valet'         => [
                'bin'           => $bin = $this->valetBinPath($composer),
                'path'          => join_paths($_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '', '.config/valet'),
                /**
                 * It is possible that the valet tld is not set yet, so we need to check if the valet bin exists
                 * and if it does, we can get the tld from the valet bin, otherwise we use the default tld.
                 *
                 * It is safe to assume that when the valet bin doesn't exist, the default tld is 'test'
                 */
                'tld'           => is_file($bin) ? ($this->exec("$bin tld") ?: ValetConfig::Tld) : ValetConfig::Tld,
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

    private function exec(string $command): ?string
    {
        $result = Process::timeout(3)->command($command)->run();
        if ($result->successful()) {
            return trim($result->output());
        }

        return null;
    }
}
