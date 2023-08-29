<?php

namespace App\Step;

use App\Config\Config;
use App\Execution\Runner;
use Illuminate\Support\Facades\File;

class ShadowEnvStep implements StepInterface
{
    public function __construct()
    {
    }

    public function name(): string
    {
        return 'Initialize Shadowenv';
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
        if (!$this->init($runner->config())) {
            return false;
        }

        if($this->createDefaultLispFile($runner->config()) && $this->createGitIgnoreFile($runner->config())) {
            return $runner->exec("cd {$runner->config()->cwd()} && /opt/homebrew/bin/shadowenv trust");
        }

        return false;
    }

    private function init(Config $config): bool
    {
        if (File::isDirectory($config->cwd($this->path()))) {
            return true;
        }

        return File::makeDirectory($config->cwd($this->path()), 0755, true);
    }

    private function createDefaultLispFile(Config $config): bool
    {
        return File::put($config->cwd($this->path('000_default.lisp')), $this->defaultContent());
    }

    private function createGitIgnoreFile(Config $config): bool
    {
        return File::put($config->cwd($this->path('.gitignore')), $this->gitIgnoreContent());
    }

    public function done(Runner $runner): bool
    {
        return $this->run($runner);
    }

    private function path(?string $path = null): string
    {
        if ($path) {
            return ".shadowenv.d/$path";
        }

        return '.shadowenv.d';
    }

    private function defaultContent(): string
    {
        $binPath = Config::OP_PATH . '/bin';
        $opPath = Config::OP_PATH;
        return <<<EOF
(env/prepend-to-pathlist "PATH" "./$binPath")
(env/set "PHP_INI_SCAN_DIR" "./$opPath/php.d")
EOF;
    }

    private function gitIgnoreContent(): string
    {
        return <<<EOF
/.*
!/.gitignore
EOF;
    }
}
