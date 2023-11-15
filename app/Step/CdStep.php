<?php

namespace App\Step;

use App\Config\Config;
use App\Execution\Runner;
use Illuminate\Support\Facades\Process;

class CdStep implements StepInterface
{
    public const DEFAULT_SHELL = '/bin/bash';

    protected string $path;

    public function __construct(string $repo)
    {
        $this->path = Config::sourcePath($repo);
    }

    public function name(): ?string
    {
        return null;
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
        if (!is_dir($this->path)) {
            $runner->io()->error("Directory does not exists.");

            return false;
        }

        if (getcwd() === $this->path) {
            return true;
        }

        $shell = self::DEFAULT_SHELL;
        if ($defaultShell = getenv('SHELL')) {
            $shell = $defaultShell;
        }

        Process::tty()->forever()->path($this->path)->run([$shell]);

        return true;
    }

    public function done(Runner $runner): bool
    {
        return false;
    }

    public function id(): string
    {
        return "cd-$this->path";
    }
}
