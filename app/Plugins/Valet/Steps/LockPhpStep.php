<?php

namespace App\Plugins\Valet\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Illuminate\Support\Str;

class LockPhpStep implements Step
{
    public readonly string | float $id;

    private const PHP_VERSION_MAP = [
        '8.3' => 'php',
        '8.2' => 'php@8.2',
        '8.1' => 'php@8.1',
        '8.0' => 'php@8.0',
        '7.4' => 'php@7.4',
    ];

    public function __construct(protected readonly string $version, protected readonly array $environment = [])
    {
        $this->id = Str::random(10);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function priority(): int
    {
        return Step::PRIORITY_HIGH;
    }

    public function name(): string
    {
        return 'Lock PHP version';
    }

    public function command(): ?string
    {
        return null;
    }

    public function checkCommand(): ?string
    {
        return null;
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

        /**
         * Add the current PHP path to the $PATH variable so that all the PHP
         * related binaries are available.
         */
        $config->paths->push(dirname($sourcePhpPath));

        $sourcePhpPath = escapeshellarg($sourcePhpPath);

        return $runner->exec("mkdir -p $binDir && ln -sf $sourcePhpPath $binPath");
    }

    public function done(Runner $runner): bool
    {
        return $this->run($runner);
    }
}