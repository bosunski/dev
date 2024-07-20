<?php

namespace App\Plugins\Core\Steps;

use App\Config\Config;
use App\Config\Project\Definition;
use App\Exceptions\UnknownShellException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;

class CdStep implements Step
{
    # Use the appropriate shell for the current environment
    public const DEFAULT_SHELL = '/bin/bash';

    protected string $path;

    public function __construct(protected string $source, protected string $search)
    {
    }

    public static function fromDefinition(Definition $definition): self
    {
        return new self($definition->source, $definition->repo);
    }

    public function name(): ?string
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
                ->name(["*$this->search*", "*$this->search", "$this->search*"]);

            foreach ($finder as $directory) {
                return $this->cd($runner, $directory->getPathname());
            }

            $runner->io()->error("Unable to find a project matching $this->search.");

            return false;
        }

        $project = new Definition($this->search, $this->source);
        $path = Config::sourcePath($project->repo, $project->source);

        if (! is_dir($path)) {
            $runner->io()->error('Directory does not exists.');

            return false;
        }

        if (getcwd() === $path) {
            return true;
        }

        return $this->cd($runner, $path);
    }

    private function cd(Runner $runner, string $path): bool
    {
        $shell = $runner->shell(null);
        if (! $shell) {
            throw new UnknownShellException();
        }

        Process::tty()->forever()->env(['DEV_SHELL' => '1'])->path($path)->run("exec {$shell['bin']}");

        return true;
    }

    private function isSinglePath(): bool
    {
        return str($this->search)->explode('/')->filter()->containsOneItem();
    }

    public function done(Runner $runner): bool
    {
        return false;
    }

    public function id(): string
    {
        return "cd-$this->search";
    }
}
