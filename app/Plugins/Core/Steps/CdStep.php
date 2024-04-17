<?php

namespace App\Plugins\Core\Steps;

use App\Config\Config;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;

class CdStep implements Step
{
    public const DEFAULT_SHELL = '/bin/bash';

    protected string $path;

    public function __construct(private readonly string $repo, private readonly string $source)
    {
        $this->path = Config::sourcePath($repo, $this->source);
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
        if ($this->isSinglePath()) {
            $in = Config::sourcePath(source: $this->source);
            $finder = Finder::create()
                ->in($in)
                ->ignoreVCS(true)
                ->ignoreDotFiles(true)
                ->ignoreUnreadableDirs()
                ->depth(1)
                ->directories()
                ->name(["*{$this->repo}*", "*{$this->repo}", "{$this->repo}*"]);

            foreach ($finder as $directory) {
                $this->path = $directory->getPathname();

                return $this->cd();
            }

            $runner->io()->error("Unable to find a project matching $this->repo.");

            return false;
        }

        if (! is_dir($this->path)) {
            $runner->io()->error('Directory does not exists.');

            return false;
        }

        if (getcwd() === $this->path) {
            return true;
        }

        return $this->cd();
    }

    private function cd(): bool
    {
        $shell = self::DEFAULT_SHELL;
        if ($defaultShell = getenv('SHELL')) {
            $shell = $defaultShell;
        }

        Process::tty()->forever()->env(['DEV_SHELL' => '1'])->path($this->path)->run("exec $shell");

        return true;
    }

    private function isSinglePath(): bool
    {
        return str($this->repo)->explode('/')->filter()->containsOneItem();
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