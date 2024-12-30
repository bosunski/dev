<?php

namespace App\Plugins\Core\Steps\ShadowEnv;

use App\Config\Config;
use App\Dev;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ShadowEnvStep implements Step
{
    public function __construct(protected readonly Dev $dev)
    {
    }

    public function name(): string
    {
        return 'Initialize Shadowenv';
    }

    public function run(Runner $runner): bool
    {
        if (! $runner->config()->isDevProject()) {
            return true;
        }

        return $this->dev->updateEnvironment();
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
        return (bool) File::put($config->cwd($this->path('000_default.lisp')), $this->defaultContent($config));
    }

    private function createGitIgnoreFile(Config $config): bool
    {
        return (bool) File::put($config->cwd($this->path('.gitignore')), $this->gitIgnoreContent());
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
        return view('shadowenv.default', [
            'paths' => $this->dev->paths(),
            'envs'  => $this->dev->envs(),
        ])->render();
    }

    private function gitIgnoreContent(): string
    {
        return <<<'EOF'
.*
!.gitignore
EOF;
    }

    public function id(): string
    {
        return Str::random(10);
    }
}
