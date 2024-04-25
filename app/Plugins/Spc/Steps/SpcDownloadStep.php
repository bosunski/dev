<?php

namespace App\Plugins\Spc\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Spc\Config\SpcConfig;

class SpcDownloadStep implements Step
{
    public function __construct(protected readonly SpcConfig $config)
    {
    }

    public function id(): string
    {
        return "spc-download-{$this->config->md5}";
    }

    public function name(): string
    {
        return 'Download SPC Deps and Extensions';
    }

    public function run(Runner $runner): bool
    {
        $spcGlobalPath = $this->config->phpPath();

        if (! is_dir($spcGlobalPath)) {
            mkdir($spcGlobalPath, recursive: true);
        }

        $command = "{$this->config->bin()} download --debug --with-php={$this->config->phpVersion}";
        foreach ($this->config->sources as $extensionOrLib => $url) {
            $command .= " -U '$extensionOrLib:$url'";
        }

        $command .= " --for-extensions='" . implode(',', $this->config->extensions) . "'";

        return $runner->exec($command, $spcGlobalPath);
    }

    public function done(Runner $runner): bool
    {
        return is_file($this->config->phpPath('downloads/.lock.json'));
    }
}
