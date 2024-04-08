<?php

namespace App\Plugins\Core\Steps;

use App\Config\Config;
use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CloneStep implements Step
{
    private const GIT_URL_REGEX = '/^(https|git)(:\/\/|@)([^\/:]+)[\/:]([^\/:]+)\/(.+).git$/';

    private const REPO_LOCATION = 'src';

    private readonly string $owner;

    private readonly string $repo;

    /**
     * @param string $repoFullName
     * @param string $host
     * @param string[] $args
     * @param null|string $root
     * @param bool $update
     * @return void
     * @throws UserException
     */
    public function __construct(
        string $repoFullName,
        private readonly string $host,
        private readonly array $args = [],
        private readonly ?string $root = null,
        private readonly bool $update = false,
    ) {
        [$this->owner, $this->repo] = self::parseService($repoFullName);
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
        return 'cd ' . self::REPO_LOCATION . ' && git status';
    }

    public function run(Runner $runner): bool
    {
        $clonePath = $this->clonePath($runner->config());
        if (File::isDirectory($clonePath)) {
            $runner->io()->info("Repository already exists at $clonePath");

            return ! $this->update || $this->pullChanges($runner, $clonePath);
        }

        File::makeDirectory($clonePath, recursive: true);
        $gitArgs = '';
        if (! empty($this->args)) {
            $gitArgs = ' ' . implode(' ', $this->args);
        }

        $result = $runner->exec("git clone$gitArgs {$this->cloneUrl()} $clonePath");

        if (! $result) {
            File::deleteDirectory($clonePath);
        }

        return $result;
    }

    public function pullChanges(Runner $runner, string $clonePath): bool
    {
        return $runner->exec('git reset --hard HEAD && git pull', $clonePath);
    }

    protected function clonePath(Config $config): string
    {
        return $config->sourcePath($this->ownerRepo(), root: $this->root);
    }

    protected function ownerRepo(): string
    {
        return "$this->owner/$this->repo";
    }

    protected function cloneUrl(): string
    {
        return 'https://' . $this->host . "/{$this->ownerRepo()}.git";
    }

    public function done(Runner $runner): bool
    {
        return false;
    }

    /**
     * @return array{string, string}
     *
     * @throws UserException
     */
    protected static function parseService(string $service): array
    {
        if (self::isUrl($service)) {
            preg_match("/^(https?:\/\/)?(www\.)?github\.com\/(?<owner>[a-zA-Z0-9-]+)\/(?<repo>[a-zA-Z0-9-]+)(\.git)?$/", $service, $matches);

            if (count($matches) == 0 || ! isset($matches['owner']) || ! isset($matches['repo'])) {
                throw new UserException('Invalid GitHub repository URL');
            }

            $details = [$matches['owner'], $matches['repo']];

            $service = self::parseUrl($service);
        }

        if (self::isPath($service)) {
            $details = self::parsePath($service);
        }

        if (empty($details)) {
            throw new UserException('Invalid repository');
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
