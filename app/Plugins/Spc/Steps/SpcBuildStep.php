<?php

namespace App\Plugins\Spc\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Spc\Config\SpcConfig;
use Illuminate\Support\Facades\File;

class SpcBuildStep implements Step
{
    public function __construct(protected SpcConfig $config)
    {
    }

    public function id(): string
    {
        return "spc-build-{$this->config->md5}";
    }

    public function name(): string
    {
        return 'Build Static Binaries using SPC';
    }

    public function run(Runner $runner): bool
    {
        $this->ensureCMakeIsInstalled($runner);

        $result = $runner->spawn($this->config->buildCommand(), $this->config->phpPath())->wait()->successful();
        if ($result) {
            return true;
        }

        File::deleteDirectory($this->config->phpPath('buildroot'));

        return false;
    }

    protected function ensureCMakeIsInstalled(Runner $runner): void
    {
        if (! $runner->config()->isDarwin()) {
            return;
        }

        $runner->spawn('brew install cmake')->wait()->throw();
    }

    public function done(Runner $runner): bool
    {
        return is_file($this->config->phpPath('buildroot/bin/php'));
    }
}
