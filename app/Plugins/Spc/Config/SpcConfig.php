<?php

namespace App\Plugins\Spc\Config;

use App\Config\Config as DevConfig;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugins\Spc\Steps\SpcBuildStep;
use App\Plugins\Spc\Steps\SpcCacheStep;
use App\Plugins\Spc\Steps\SpcDownloadStep;
use App\Plugins\Spc\Steps\SpcInstallStep;
use App\Plugins\Spc\Steps\SpcLinkStep;
use Exception;

/**
 * @phpstan-type PhpConfig array{
 *      version?: string,
 *      preset?: string,
 *      extensions?: string[],
 *      sources: array<string, string>
 * }
 *
 * @phpstan-type RawSpcCombineConfig array{
 *     input: string,
 *     output: string
 * }
 *
 * @phpstan-type RawSpcConfig array{
 *      php: PhpConfig,
 *      combine?: RawSpcCombineConfig
 * }
 *
 * @phpstan-type SpcEnvironment array{bin: string, pecl: string, dir: string, version: string, extensionPath: string, cwd: string, home: string}
 */
class SpcConfig implements Config
{
    public const DefaultPhpVersion = '8.2';

    public const SupportedPhpVersions = ['8.0', '8.1', '8.2', '8.3'];

    public const DefaultExtensions = [
        'bcmath',
        'calendar',
        'ctype',
        'curl',
        'dba',
        'dom',
        'exif',
        'session',
        'filter',
        'fileinfo',
        'iconv',
        'mbstring',
        'mbregex',
        'openssl',
        'pcntl',
        'pdo',
        'pdo_mysql',
        'pdo_sqlite',
        'phar',
        'posix',
        'readline',
        'simplexml',
        'sockets',
        'sqlite3',
        'tokenizer',
        'xml',
        'xmlreader',
        'xmlwriter',
        'zip',
        'zlib',
        'sodium',
    ];

    public readonly string $phpVersion;

    /**
     * @var PhpConfig['extensions']
     */
    public readonly array $extensions;

    /**
     * @var PhpConfig['sources']
     */
    public readonly array $sources;

    /**
     * @param RawSpcConfig $config
     * @return void
     */
    public function __construct(protected readonly array $config, protected readonly DevConfig $devConfig)
    {
        $this->phpVersion = $config['php']['version'] ?? self::DefaultPhpVersion;
        $this->extensions = array_merge(
            $this->config['php']['extensions'] ?? [],
            $this->getPresetExtensions($config['php']['preset'] ?? '')
        );

        $this->sources = $this->config['php']['sources'] ?? [];
    }

    /**
     * @param string $preset
     * @return string[]
     */
    public function getPresetExtensions(string $preset): array
    {
        if ($preset === 'common') {
            return self::DefaultExtensions;
        }

        return [];
    }

    /**
     * @throws Exception
     */
    public function steps(): array
    {
        /**
         * This default steps ensures that the desired PHP version is installed,
         * so we will always add them. The steps will, by themselves, decide whether
         * to run or not based on Step::done() method.
         */
        return [
            new SpcInstallStep(),
            new SpcDownloadStep($this),
            new SpcBuildStep($this),
            new SpcLinkStep($this),
            new SpcCacheStep($this),
        ];
    }

    public function cachePath(): string
    {
        return $this->devConfig->globalPath("spc/$this->phpVersion/lock");
    }

    public function lockPath(): string
    {
        return $this->devConfig->globalPath("spc/$this->phpVersion/lock");
    }

    public function checksum(): string
    {
        $cacheContent = implode(',', (array) $this->extensions);

        foreach ($this->sources as $extensionOrLib => $url) {
            $cacheContent .= "::$extensionOrLib:$url";
        }

        return md5($cacheContent);
    }

    public function bin(): string
    {
        return $this->devConfig->globalPath('bin/spc');
    }

    public function phpPath(?string $path = null): string
    {
        return $this->devConfig->globalPath("spc/$this->phpVersion/$path");
    }

    /**
     * @return RawSpcCombineConfig|array{}
     */
    public function combine(): array
    {
        return $this->config['combine'] ?? [];
    }

    public function sfx(): string
    {
        return $this->phpPath('buildroot/bin/micro.sfx');
    }
}