<?php

namespace App\Plugins\Spc\Concerns;

use App\Plugins\Spc\Config\SpcConfig;
use App\Plugins\Spc\SpcStepResolver;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
* @phpstan-import-type SpcEnvironment from SpcConfig
* @phpstan-import-type RawSpcConfig from SpcConfig
 */
trait ResolvesEnvironment
{
    /**
     * @param RawSpcConfig $config
     * @return SpcEnvironment
     * @throws ProcessFailedException
     * @throws ProcessTimedOutException
     * @throws RuntimeException
     */
    private function resolveEnvironmentSettings(array $config): array
    {
        $configVersion = (string) Arr::get($config, 'php.version', Arr::get($config, 'php', ''));
        $bin = self::phpPath($configVersion) ?: trim(`which php` ?? '');

        return [
            'bin'           => $bin ?: trim(`which php` ?? ''),
            'pecl'          => dirname($bin) . '/pecl',
            'dir'           => dirname($bin, 2),
            'extensionPath' => $this->currentPhpExtensionPath($bin),
            'version'       => SpcStepResolver::PHP_VERSION_MAP[$configVersion] ?? $configVersion,
            'cwd'           => $this->dev->config->cwd(),
            'home'          => $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null,
        ];

    }

    protected static function phpPath(string $version): string
    {
        $source = SpcStepResolver::PHP_VERSION_MAP[$version] ?? null;
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
        try {
            return Process::timeout(3)->command($command)->run()->throw();
        } catch (ProcessFailedException $exception) {
            // TODO: Fix this
            // For weird reasons, the command is returning exit code -1 and causing the exception to be thrown
            // This is a temporary fix to return the result instead of throwing the exception
            if ($exception->result->exitCode() == -1) {
                return $exception->result;
            }

            throw $exception;
        }
    }
}
