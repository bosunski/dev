<?php

namespace App\Plugins\Core\Steps;

use App\Config\Config;
use App\Config\Project\Definition;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;

class CdStep implements Step
{
    public const DEFAULT_SHELL = '/bin/bash';

    protected string $path;

    public function __construct(protected Definition $project)
    {
        $this->path = Config::sourcePath($project->repo, $project->source);
    }

    public function name(): ?string
    {
        return null;
    }

    public function run(Runner $runner): bool
    {
        if ($this->isSinglePath()) {
            $in = Config::sourcePath(source: $this->project->source);
            $finder = Finder::create()
                ->in($in)
                ->ignoreVCS(true)
                ->ignoreDotFiles(true)
                ->ignoreUnreadableDirs()
                ->depth(1)
                ->directories()
                ->name(["*{$this->project->repo}*", "*{$this->project->repo}", "{$this->project->repo}*"]);

            foreach ($finder as $directory) {
                $this->path = $directory->getPathname();

                return $this->cd();
            }

            $runner->io()->error("Unable to find a project matching {$this->project->repo}.");

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
        return str($this->project->repo)->explode('/')->filter()->containsOneItem();
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
