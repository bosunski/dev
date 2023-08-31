<?php

namespace App\Step\Git;

use App\Config\Config;
use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Step\StepInterface;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CloneStep implements StepInterface
{
    private const GIT_URL_REGEX = '/^(https|git)(:\/\/|@)([^\/:]+)[\/:]([^\/:]+)\/(.+).git$/';

    private const REPO_LOCATION = "src";

    private const DEFAULT_SOURCE_HOST = "github.com";

    public function __construct(private readonly string $owner, private readonly string $repo)
    {
    }

    public function name(): string
    {
        return "Cloning $this->repo";
    }

    public function command(): ?string
    {
        return "git clone $this->repo " . self::REPO_LOCATION;
    }

    public function checkCommand(): ?string
    {
        return "cd " . self::REPO_LOCATION . " && git status";
    }

    public function run(Runner $runner): bool
    {
        $clonePath = $this->clonePath($runner->config());
        $runner->io()->info("Cloning {$this->cloneUrl()} to $clonePath");

        if (File::exists($clonePath)) {
            $runner->io()->info("Repository already exists");

            return false;
        }

        try {
            File::makeDirectory($clonePath, recursive: true);
            return $runner->exec("git clone {$this->cloneUrl()} $clonePath");
        } catch (ProcessFailedException) {
            File::deleteDirectory($clonePath);

            return false;
        }
    }

    protected function clonePath(Config $config): string
    {
        return $config->sourcePath($this->ownerRepo());
    }

    protected function ownerRepo(): string
    {
        return "$this->owner/$this->repo";
    }

    protected function cloneUrl(): string
    {
        return "https://" . self::DEFAULT_SOURCE_HOST . "/{$this->ownerRepo()}.git";
    }

    public function done(Runner $runner): bool
    {
        return is_dir($this->clonePath($runner->config()));
    }

    /**
     * @throws UserException
     */
    public static function parseService(string $service): array
    {
        if (self::isUrl($service)) {
            preg_match("/^(https?:\/\/)?(www\.)?github\.com\/(?<owner>[a-zA-Z0-9-]+)\/(?<repo>[a-zA-Z0-9-]+)(\.git)?$/", $service, $matches);

            if (count($matches) == 0 || !isset($matches['owner']) || !isset($matches['repo'])) {
                throw new UserException("Invalid GitHub repository URL");
            }

            $details = [$matches['owner'], $matches['repo']];

            $service = self::parseUrl($service);
        }

        if (self::isPath($service)) {
            $details = self::parsePath($service);
        }

        if (empty($details)) {
            throw new UserException("Invalid repository");
        }

        return $details;
    }

    private static function parseUrl(string $url): string
    {
        preg_match(self::GIT_URL_REGEX, $url, $matches);

        return $matches[5];
    }

    private static function parsePath(string $path): array
    {
        return explode('/', $path);
    }

    private static function isUrl(string $service): bool
    {
        return Str::startsWith($service, ['http://', 'https://']);
    }

    private static function isPath(string $service): bool
    {
        return count(explode('/', $service)) == 2;
    }
}
