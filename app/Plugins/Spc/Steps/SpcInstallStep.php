<?php

namespace App\Plugins\Spc\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use RuntimeException;

class SpcInstallStep implements Step
{
    public function __construct(private bool $force = false)
    {
    }

    public function id(): string
    {
        return 'spc-install';
    }

    public function name(): string
    {
        return 'Install SPC binary';
    }

    public function run(Runner $runner): bool
    {
        $binDir = $runner->config()->globalPath('bin');
        @mkdir($binDir, recursive: true);

        $filename = $this->getFilename();
        $script = <<<SCRIPT
            set -xe
            curl -L -o $filename https://github.com/crazywhalecc/static-php-cli/releases/latest/download/$filename
            tar -xf $filename
            chmod +x spc
            rm $filename
        SCRIPT;

        return $runner->exec($script, $binDir);
    }

    private function getFilename(): string
    {
        $os = php_uname('s');
        $os = match($os) {
            'Darwin' => 'macos',
            'Linux'  => 'linux',
            default  => throw new RuntimeException("Unsupported OS: $os for SPC"),
        };

        $arch = php_uname('m');
        $arch = match($arch) {
            'aarch64', 'arm64' => 'aarch64',
            default => throw new RuntimeException("Unsupported architecture: $arch for SPC"),
        };

        return "spc-$os-$arch.tar.gz";
    }

    public function done(Runner $runner): bool
    {
        return ! $this->force && is_file($runner->config()->globalPath('bin/spc'));
    }
}
