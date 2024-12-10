<?php

namespace App\Plugins\Core\Steps;

use App\Config\Config;
use App\Config\Project\Definition;
use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Illuminate\Support\Facades\File;

class CloneStep implements Step
{
    /**
     * @param Definition $project
     * @param string|string[] $args
     * @param null|string $root
     * @param bool $update
     * @return void
     * @throws UserException
     */
    public function __construct(
        protected Definition $project,
        private readonly array|string $args = [],
        private readonly ?string $root = null,
        private readonly bool $update = false,
    ) {
    }

    public function id(): string
    {
        return "git-clone-{$this->project->repo}";
    }

    public function name(): ?string
    {
        return null;
    }

    public function run(Runner $runner): bool
    {
        $clonePath = $this->clonePath($runner->config());
        if (File::isDirectory($clonePath)) {
            $runner->io()->writeln("Repository already exists at $clonePath");

            return ! $this->update || $this->pullChanges($runner, $clonePath);
        }

        File::makeDirectory($clonePath, recursive: true);
        $gitArgs = '';
        if (! empty($this->args)) {
            $gitArgs = ' ';
            $gitArgs .= is_array($this->args) ? implode(' ', $this->args) : $this->args;
        }

        if ($this->project->ref) {
            $gitArgs .= " --branch {$this->project->ref}";
        }

        $result = $runner->withoutEnv()->exec("git clone$gitArgs {$this->project->url} $clonePath");
        if (! $result) {
            File::deleteDirectory($clonePath);
        }

        return $result;
    }

    public function pullChanges(Runner $runner, string $clonePath): bool
    {
        return $runner->withoutEnv()->exec('git reset --hard HEAD && git pull', $clonePath, [
            /**
             * Both of these variables prevents git from looking for the .git directory in the parent directories
             * so as to avoid any unexpected behavior.
             */
            'GIT_DIR'       => $clonePath . DIRECTORY_SEPARATOR . '.git',
            'GIT_WORK_TREE' => $clonePath,
        ]);
    }

    protected function clonePath(Config $config): string
    {
        return $config->sourcePath($this->project->repo, $this->project->source, $this->root);
    }

    public function done(Runner $runner): bool
    {
        return false;
    }
}
