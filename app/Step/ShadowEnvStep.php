<?php

namespace App\Step;

use App\Config\Config;
use App\Execution\Runner;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

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
        if (!$runner->config()->isDevProject()) {
            return true;
        }

        if (!$this->init($runner->config())) {
            return false;
        }

        if($this->createDefaultLispFile($runner->config()) && $this->createGitIgnoreFile($runner->config())) {
            /**
             * We cannot use the Runner::exec() to run this because it uses `shadowenv exec` which
             * can only be used after the path has been trusted. At this point, the path is not trusted
             * so, we will run this directly.
             */
            return Process::path($runner->config()->cwd())->run(["/opt/homebrew/bin/shadowenv", "trust"])->successful();
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
        return File::put($config->cwd($this->path('000_default.lisp')), $this->defaultContent($config));
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

    private function defaultContent(Config $config): string
    {
        $binPath = $config->devPath('bin');
        $opPath = $config->devPath('php.d');
        return <<<EOF
(env/prepend-to-pathlist "PATH" "$binPath")
(env/set "PHP_INI_SCAN_DIR" "$opPath:\$PHP_INI_SCAN_DIR")
EOF;
    }

    private function gitIgnoreContent(): string
    {
        return <<<EOF
.*
!.gitignore
EOF;
    }

    public function id(): string
    {
        return Str::random(10);
    }
}
