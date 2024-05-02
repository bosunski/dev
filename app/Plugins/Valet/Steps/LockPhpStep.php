<?php

namespace App\Plugins\Valet\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Config\ValetConfig;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type RawValetEnvironment from ValetConfig
*/
class LockPhpStep implements Step
{
    private const PHP_VERSION_MAP = [
        '8.3' => 'php',
        '8.2' => 'php@8.2',
        '8.1' => 'php@8.1',
        '8.0' => 'php@8.0',
        '7.4' => 'php@7.4',
    ];

    /**
     * @param string $version
     * @param RawValetEnvironment $environment
     * @return void
     */
    public function __construct(protected readonly string $version, protected readonly array $environment)
    {
    }

    public function id(): string
    {
        return Str::random(10);
    }

    public function name(): string
    {
        return 'Lock PHP version';
    }

    public function run(Runner $runner): bool
    {
        $config = $runner->config();

        if (! isset(self::PHP_VERSION_MAP[$this->version])) {
            $runner->io()->error("Unknown PHP version: $this->version." . PHP_EOL . 'Supported versions: ' . implode(', ', array_keys(self::PHP_VERSION_MAP)));

            return false;
        }

        $sourcePhpPath = $this->environment['bin'];
        if (! $sourcePhpPath || ! is_file($sourcePhpPath)) {
            $runner->io()->error("PHP $this->version is not installed");

            return false;
        }

        $binDir = $config->path('bin');
        $binPath = $config->path('bin/php');

        $sourcePhpPath = escapeshellarg($sourcePhpPath);

        return $runner->exec("mkdir -p $binDir && ln -sf $sourcePhpPath $binPath");
    }

    public function done(Runner $runner): bool
    {
        return $this->run($runner);
    }
}
