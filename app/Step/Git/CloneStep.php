<?php

namespace App\Step\Git;

use App\Config\Config;
use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Step\StepInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CloneStep implements StepInterface
{
    private const GIT_URL_REGEX = '/^(https|git)(:\/\/|@)([^\/:]+)[\/:]([^\/:]+)\/(.+).git$/';

    private const REPO_LOCATION = "src";

    public function __construct(private readonly string $owner, private readonly string $repo, private readonly string $host, private readonly array $args = [])
    {
    }

    public function id(): string
    {
        return "git-clone-$this->owner-$this->repo";
    }

    public function name(): ?string
    {
        return null;
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
        if (File::isDirectory($clonePath)) {
            $runner->io()->info("Repository already exists at $clonePath");

            return true;
        }

        File::makeDirectory($clonePath, recursive: true);
        $gitArgs = "";
        if (!empty($this->args)) {
            $gitArgs = " " . implode(' ', $this->args);
        }

        $result = $runner->exec("git clone$gitArgs {$this->cloneUrl()} $clonePath");

        if (!$result) {
            File::deleteDirectory($clonePath);
        }

        return $result;
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
        return "https://" . $this->host . "/{$this->ownerRepo()}.git";
    }

    public function done(Runner $runner): bool
    {
        return false;
    }

    /**
     * @return array{owner: string, repo: string}
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
