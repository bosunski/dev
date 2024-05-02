<?php

namespace App\Config;

use App\Exceptions\Config\InvalidConfigException;
use App\Exceptions\UserException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type Command array{
 *    desc?: string,
 *    run: string,
 *    signature?: string,
 * }
 *
 * @phpstan-type Serve string | array{
 *   run: string,
 *   env?: string | false,
 * }
 *
 * @phpstan-type Site string
 *
 * @phpstan-type Script array{
 *      desc?: string,
 *      run: string,
 *      'met?'?: string
 * }
 *
 * @phpstan-type Step array<string, mixed> | Script
 *
 * @phpstan-type Up array<int, array<string | "script", Step>>
 *
 * @phpstan-type RawConfig array{
 *      name?: string,
 *      up?: Up,
 *      steps?: Up,
 *      commands?: array<string, Command>,
 *      serve?: array<string, Serve>|string,
 *      sites?: array<string, string>,
 *      env?: array<string, string>,
 *      projects?: non-empty-string[]
 * }
 */
class Config
{
    public const OP_PATH = '.dev';

    private const REPO_LOCATION = 'src';

    private const DEFAULT_SOURCE_HOST = 'github.com';

    public const FileName = 'dev.yml';

    public const LockFiles = [
        'composer.json',
        'package.json',
        'composer.lock',
        'yarn.lock',
        'package-lock.json',
        self::FileName,
    ];

    /**
     * @var array{
     *      disabled: string[],
     *      locks: array<string, string>,
     *      env: array<string, string>,
     * }
     */
    public array $settings = [
        'locks'    => [],
        'disabled' => [],
        'env'      => [],
    ];

    private readonly UpConfig $up;

    protected Env $env;

    /**
     * @param string $path
     * @param RawConfig|array{} $config
     * @param bool $isRoot
     * @return void
     */
    public function __construct(
        protected readonly string $path,
        protected array $config,
        public bool $isRoot = false,
        public readonly ?string $root = null,
    ) {
        $this->readSettings();

        $this->up = new UpConfig($config['steps'] ?? $config['up'] ?? []);
        $this->env = new Env(collect($this->config['env'] ?? []), getenv());
    }

    private function readSettings(): void
    {
        $jsonPath = $this->cwd(self::OP_PATH . DIRECTORY_SEPARATOR . 'config.json');
        if (file_exists($jsonPath) && $content = @file_get_contents($jsonPath)) {
            $config = json_decode($content, true);
            if ($config === null || ! is_array($config)) {
                throw new UserException("Failed to parse $jsonPath. Please check the file for syntax errors.");
            }

            // @phpstan-ignore-next-line
            $this->settings = array_replace_recursive($this->settings, $config);
        }
    }

    public function writeSettings(): void
    {
        $jsonPath = $this->cwd(self::OP_PATH . DIRECTORY_SEPARATOR . 'config.json');
        file_put_contents($jsonPath, json_encode($this->settings, JSON_PRETTY_PRINT));
    }

    public function root(bool $isRoot = true): Config
    {
        $this->isRoot = $isRoot;

        return $this;
    }

    public function getName(): string
    {
        return $this->config['name'] ?? '';
    }

    /**
     * @param bool $all
     * @return Collection<int, non-empty-string>
     */
    public function projects(bool $all = false): Collection
    {
        // @phpstan-ignore-next-line
        return collect($this->config['projects'] ?? [])
            ->filter(fn (string $project) => $all || ! in_array($project, $this->settings['disabled']))
                /** @return non-empty-string */
            ->map(function (string $project): string {
                if ($project === $this->projectName()) {
                    throw new UserException('You cannot reference the current project in its own config!');
                }

                Assert::notEmpty($project);

                return $project;
            })->unique();
    }

    /**
     * @return Collection<string, string>
     */
    public function sites(): Collection
    {
        return collect($this->config['sites'] ?? []);
    }

    /**
     * @return Collection<string, Command>
     */
    public function commands(): Collection
    {
        return collect($this->config['commands'] ?? []);
    }

    public function up(): UpConfig
    {
        return $this->up;
    }

    /**
     * @return RawConfig['up']|array{}
     */
    public function steps(): array
    {
        return $this->config['up'] ?? [];
    }

    public function path(?string $path = null): string
    {
        return $this->cwd(self::OP_PATH . DIRECTORY_SEPARATOR . ltrim($path ?? '', DIRECTORY_SEPARATOR));
    }

    public function servicePath(?string $path = null): string
    {
        return $this->cwd(self::OP_PATH . DIRECTORY_SEPARATOR . self::REPO_LOCATION . DIRECTORY_SEPARATOR . self::DEFAULT_SOURCE_HOST . DIRECTORY_SEPARATOR . trim($path ?? '', DIRECTORY_SEPARATOR));
    }

    public function devPath(?string $path = null): string
    {
        return $this->cwd(self::OP_PATH . DIRECTORY_SEPARATOR . trim($path ?? '', DIRECTORY_SEPARATOR));
    }

    public function cwd(?string $path = null): string
    {
        if ($path) {
            return $this->path . DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR);
        }

        return $this->path;
    }

    public function globalPath(?string $path = null): string
    {
        $home = $this->home() . DIRECTORY_SEPARATOR . self::OP_PATH;
        if ($path) {
            return $home . DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR);
        }

        return $home;
    }

    public static function home(): string
    {
        return (string) getenv('HOME');
    }

    public static function sourcePath(?string $path = null, ?string $source = null, ?string $root = null): string
    {
        $sourceDir = sprintf('%s/%s/%s', rtrim($root ?? self::home(), DIRECTORY_SEPARATOR), self::REPO_LOCATION, $source ?? self::DEFAULT_SOURCE_HOST);

        if ($path) {
            return $sourceDir . DIRECTORY_SEPARATOR . ltrim($path, '/');
        }

        return $sourceDir;
    }

    public function projectName(): string
    {
        return Str::of($this->cwd())->after($this->sourcePath(root: $this->root))->trim('/')->toString();
    }

    public function isDevProject(): bool
    {
        return ! empty($this->config);
    }

    /**
     * @throws UserException
     */
    public static function read(string $path, string $root = null): Config
    {
        return new Config($path, self::parseYaml($path), root: $root);
    }

    /**
     * @throws UserException
     */
    public static function fromPath(string $path): Config
    {
        return static::read($path);
    }

    /**
     * @throws UserException
     */
    public static function fromProjectName(string $path, ?string $root = null): Config
    {
        $root = $root ?? sprintf('%s/%s', getcwd(), self::OP_PATH);

        return static::read(static::sourcePath($path, root: $root), $root);
    }

    /**
     * @return RawConfig|array{}
     * @throws UserException
     */
    private static function parseYaml(string $path): array
    {
        if (! file_exists(self::fullPath($path))) {
            return [];
        }

        try {
            return (array) Yaml::parseFile(self::fullPath($path));
        } catch (ParseException $e) {
            throw new InvalidConfigException($e);
        }
    }

    private static function fullPath(string $path): string
    {
        return $path . DIRECTORY_SEPARATOR . self::FileName;
    }

    /**
     * @return array<string, Serve>|string
     */
    public function getServe(): array|string
    {
        if (! isset($this->config['serve'])) {
            return [];
        }

        return $this->config['serve'];
    }

    /**
     * @return Collection<string, string>
     */
    public function envs(): Collection
    {
        [$resolved, $prompted] = $this->env->resolve($this->settings['env']);
        $this->settings['env'] = $prompted;

        /**
         * If we had to prompt for any values, we need to persist the settings
         * to disk. So we can use them next and not prompt again.
         */
        if ($this->env->wasPrompted()) {
            $this->writeSettings();
        }

        return $resolved;
    }

    public function isDebug(): bool
    {
        return false;
    }
}
