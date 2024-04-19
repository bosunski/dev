<?php

namespace App\Config;

use App\Exceptions\UserException;
use App\Utils\Values;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

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
 *      commands?: array<string, Command>,
 *      serve?: array<string, Serve>,
 *      sites?: array<string, string>,
 *      env?: array<string, string>,
 *      projects: non-empty-string[]
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
     * @var array{disabled?: string[], locks: array<string, string>}
     */
    public array $settings = [];

    private readonly UpConfig $up;

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

        $this->up = new UpConfig($config['up'] ?? []);
    }

    private function readSettings(): void
    {
        $jsonConfig = ['disabled' => []];
        $jsonPath = $this->cwd(self::OP_PATH . DIRECTORY_SEPARATOR . 'config.json');
        if (file_exists($jsonPath)) {
            $jsonConfig = array_merge($jsonConfig, json_decode(file_get_contents($jsonPath), true));
        }

        $this->settings = $jsonConfig;
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
     * @return Collection<int, string>
     */
    public function projects(bool $all = false): Collection
    {
        return collect($this->config['projects'] ?? [])->filter(fn ($service) => $all || ! in_array($service, $this->settings['disabled']))->map(function (string $service) {
            if ($service === $this->projectName()) {
                throw new UserException('You cannot reference the current project in its own config!');
            }

            return $service;
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
        return $this->cwd(self::OP_PATH . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR));
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
            return Yaml::parseFile(self::fullPath($path));
        } catch (ParseException $e) {
            throw new UserException($e->getMessage());
        }
    }

    private static function fullPath(string $path): string
    {
        return $path . DIRECTORY_SEPARATOR . self::FileName;
    }

    /**
     * @return array<string, Serve>
     */
    public function getServe(): array
    {
        $hasServe = isset($this->config['serve']);
        if (! $hasServe) {
            return [];
        }

        if (! is_array($this->config['serve'])) {
            throw new RuntimeException('Serve config should be an array');
        }

        return $this->config['serve'];
    }

    /**
     * @return Collection<string, string|null>
     */
    public function envs(): Collection
    {
        return collect($this->config['env'] ?? [])
            ->map(Values::evaluateEnv(...))
            ->map(fn ($value) => Values::substituteEnv($value, collect(getenv())));
    }

    public function isDebug(): bool
    {
        return false;
    }
}
