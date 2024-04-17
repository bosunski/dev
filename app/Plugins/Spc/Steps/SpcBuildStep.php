<?php

namespace App\Plugins\Spc\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Spc\Config\SpcConfig;

class SpcBuildStep implements Step
{
    public function __construct(protected SpcConfig $config)
    {
    }

    public function id(): string
    {
        return 'spc-build';
    }

    public function name(): string
    {
        return 'Build Static Binaries using SPC';
    }

    public function run(Runner $runner): bool
    {
        $extensions = implode(',', $this->config->extensions);

        $command = "{$this->config->bin()} build --no-strip --build-micro --build-cli --with-micro-fake-cli '$extensions'";

        return $runner->exec($command, $this->config->phpPath());
    }

    public function done(Runner $runner): bool
    {
        return $this->locked() && is_file($this->config->phpPath('buildroot/bin/php'));
    }

    public function locked(): bool
    {
        $lockPath = $this->config->lockPath();

        return is_file($lockPath) && file_get_contents($lockPath) === $this->config->checksum();
    }
}
