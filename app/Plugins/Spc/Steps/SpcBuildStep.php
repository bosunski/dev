<?php

namespace App\Plugins\Spc\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Brew\Steps\BrewStep;
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

        $cwd = getcwd();

        chdir($this->config->phpPath());
        dump($this->config->phpPath());
        if ($runner->exec(['cd', $this->config->phpPath(), '&&', ...$this->config->buildCommand()], $this->config->phpPath(), [
            'PWD' => $this->config->phpPath(),
        ])) {
            chdir($cwd);
            return true;
        }

        chdir($cwd);

        File::deleteDirectory($this->config->phpPath('buildroot'));

        return false;
    }

    protected function ensureCMakeIsInstalled(Runner $runner): void
    {
        if (! $runner->config()->isDarwin()) {
            return;
        }

        $runner->execute(new BrewStep(['cmake']));
    }

    public function done(Runner $runner): bool
    {
        return false;
    }
}
