<?php

namespace App\Plugins\Core\Steps;

use App\Config\Config;
use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

class CloneStep implements Step
{
    private const GIT_URL_REGEX = '/^(https|git)(:\/\/|@)([^\/:]+)[\/:]([^\/:]+)\/(.+).git$/';

    private const REPO_LOCATION = 'src';

    private readonly string $owner;

    private readonly string $repo;

    /**
     * @param non-empty-string $repoFullName
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
            $runner->io()->writeln("Repository already exists at $clonePath");

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
        return $runner->exec('git reset --hard HEAD && git pull', $clonePath, [
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
     * @param non-empty-string $service
     * @return array{non-empty-string, non-empty-string}
     * @throws UserException
     */
    protected static function parseService(string $service): array
    {
        /** @var array{non-empty-string, non-empty-string} $details */
        $details = [];
        if (self::isUrl($service)) {
            preg_match("/^(https?:\/\/)?(www\.)?github\.com\/(?<owner>[a-zA-Z0-9-]+)\/(?<repo>[a-zA-Z0-9-]+)(\.git)?$/", $service, $matches);

            if (count($matches) == 0 || ! isset($matches['owner']) || ! isset($matches['repo'])) {
                throw new UserException('Invalid GitHub repository URL');
            }

            Assert::notEmpty($matches['owner']);
            Assert::notEmpty($matches['repo']);

            $details = [$matches['owner'], $matches['repo']];
            $service = self::parseUrl($service);
        }

        if (self::isPath($service)) {
            $details = self::parsePath($service);
        }

        if (empty($details)) {
            throw new UserException('Invalid repository');
        }

        // @phpstan-ignore-next-line
        return $details;
    }

    /**
     * @param non-empty-string $url
     * @return non-empty-string
     */
    private static function parseUrl(string $url): string
    {
        preg_match(self::GIT_URL_REGEX, $url, $matches);

        Assert::notEmpty($matches[5]);

        return $matches[5];
    }

    /**
     * @param non-empty-string $path
     * @return non-empty-string[]
     */
    private static function parsePath(string $path): array
    {
        [$owner, $repo] = explode('/', $path, 2);

        Assert::notEmpty($owner);
        Assert::notEmpty($repo);

        return [$owner, $repo];
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
