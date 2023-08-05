<?php

namespace App\Commands;

use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class CloneCommand extends Command
{
    private const GIT_URL_REGEX = '/^(https|git)(:\/\/|@)([^\/:]+)[\/:]([^\/:]+)\/(.+).git$/';

    private const REPO_LOCATION = "src";

    private const DEFAULT_SOURCE_HOST = "github.com";

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'clone {repo}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Clones a GitHub repository';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): int
    {
        $repo = $this->argument('repo');
        $details = [];

        if ($this->isUrl()) {

            preg_match("/^(https?:\/\/)?(www\.)?github\.com\/(?<owner>[a-zA-Z0-9-]+)\/(?<repo>[a-zA-Z0-9-]+)(\.git)?$/", $repo, $matches);

            if (count($matches) == 0 || !isset($matches['owner']) || !isset($matches['repo'])) {
                $this->error("Invalid GitHub repository URL");

                return 1;
            }

            $details = [$matches['owner'], $matches['repo']];

            $repo = $this->parseUrl($repo);
        }

        if ($this->isPath()) {
            $details = $this->parsePath($repo);
        }

        if (empty($details)) {
            $this->error("Invalid repository");

            return 1;
        }

        [$owner, $repo] = $details;
        $ownerRepo = "$owner/$repo";
        $cloneUrl = "https://" . self::DEFAULT_SOURCE_HOST . "/$owner/$repo.git";
        $clonePath = sprintf("%s/%s/%s/%s", env('HOME'), self::REPO_LOCATION, self::DEFAULT_SOURCE_HOST, $ownerRepo);

        $this->info("Cloning $cloneUrl to $clonePath");

        if (File::exists($clonePath)) {
            $this->info("Repository already exists");

            return 1;
        }

        try {
            File::makeDirectory($clonePath, recursive: true);
            Process::run("git clone $cloneUrl $clonePath", function (string $type, string $output) {
                echo $output;
            })->throw();
        } catch (ProcessFailedException) {
            File::deleteDirectory($clonePath);

            $this->error("Failed to clone repository");

            return 1;
        }

        return 0;
    }

    private function parseUrl(string $url): string
    {
        preg_match(self::GIT_URL_REGEX, $url, $matches);

        return $matches[5];
    }

    private function parsePath(string $path): array
    {
        return explode('/', $path);
    }

    private function isUrl(): bool
    {
        return Str::startsWith($this->argument('repo'), ['http://', 'https://']);
    }

    private function isPath(): bool
    {
        return count(explode('/', $this->argument('repo'))) == 2;
    }
}
