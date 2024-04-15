<?php

namespace App\Plugins\Spc\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Spc\Config\SpcConfig;

class SpcLinkStep implements Step
{
    public function __construct(protected readonly SpcConfig $config)
    {
    }

    public function id(): string
    {
        return 'spc-link';
    }

    public function name(): string
    {
        return 'Link built PHP binary to current working directory';
    }

    public function run(Runner $runner): bool
    {
        if (! in_array($this->config->phpVersion, SpcConfig::SupportedPhpVersions)) {
            $runner->io()->error("Unknown PHP version: {$this->config->phpVersion}." . PHP_EOL . 'Supported versions: ' . implode(', ', SpcConfig::SupportedPhpVersions));

            return false;
        }

        $sourcePhpPath = $this->config->phpPath('buildroot/bin/php');
        if (! $sourcePhpPath || ! is_file($sourcePhpPath)) {
            $runner->io()->error("PHP {$this->config->phpVersion }is not installed please run `dev up` again");

            return false;
        }

        $config = $runner->config();
        $binDir = $config->path('bin');
        $binPath = $config->path('bin/php');

        $sourcePhpPath = escapeshellarg($sourcePhpPath);

        return $runner->exec("mkdir -p $binDir && ln -sf $sourcePhpPath $binPath");
    }

    public function done(Runner $runner): bool
    {
        $linkPath = $runner->config()->devPath('bin/php');
        $expectedTarget = $this->config->phpPath('buildroot/bin/php');

        return is_file($linkPath) && is_link($linkPath) && readlink($linkPath) === $expectedTarget;
    }
}
