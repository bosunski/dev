<?php

namespace App\Commands;

use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class CdCommand extends Command
{
    private const GIT_URL_REGEX = '/^(https|git)(:\/\/|@)([^\/:]+)[\/:]([^\/:]+)\/(.+).git$/';

    private const REPO_LOCATION = "src";

    private const DEFAULT_SOURCE_HOST = "github.com";

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'cd {repo}';

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
        chdir('app');

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
