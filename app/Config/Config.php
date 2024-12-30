<?php

namespace App\Config;

use App\Config\Project\Definition;
use App\Exceptions\Config\InvalidConfigException;
use App\Exceptions\UserException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function Illuminate\Filesystem\join_paths;

/**
 * @phpstan-type Command array{
 *    desc?: string,
 *    run: string|string[],
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
 *      name?: string,
 *      run: string|string[],
 *      'met?'?: string
 * }
 *
 * @phpstan-type Step array<string, mixed> | Script
 *
 * @phpstan-type CommandStep array<"command", non-empty-string>
 * @phpstan-type Steps array<int, array<string | "script", Step> | CommandStep>
 *
 * @phpstan-type RawConfig array{
 *      name?: string,
 *      up?: Steps,
 *      steps?: Steps,
 *      commands?: array<string, Command>,
 *      serve?: array<string, Serve>|string,
 *      sites?: array<string, string>,
 *      env?: array<string, string>,
 *      projects?: non-empty-string[]
 * }
 */
class Config
{
    public const DevDir = '.dev';

    public const SrcDir = 'src';

    public const DefaultSource = 'github.com';

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

    protected readonly string $uname;

    protected Env $env;

    /**
     * @param string $path
     * @param RawConfig|array{} $raw
     * @param bool $isRoot
     * @return void
     */
    public function __construct(
        protected readonly string $path,
        protected readonly array $raw,
        public bool $isRoot = false,
        public readonly ?string $root = null,
    ) {
        $this->readSettings();

        $this->up = new UpConfig($raw['steps'] ?? $raw['up'] ?? []);
        $this->env = new Env(collect($this->raw['env'] ?? []), getenv());

        $this->uname = php_uname('s');
    }

    private function readSettings(): void
    {
        $jsonPath = $this->cwd(self::DevDir . DIRECTORY_SEPARATOR . 'config.json');
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
        $jsonPath = $this->cwd(self::DevDir . DIRECTORY_SEPARATOR . 'config.json');
        file_put_contents($jsonPath, json_encode($this->settings, JSON_PRETTY_PRINT));
    }

    public function getName(): string
    {
        return $this->raw['name'] ?? '';
    }

    /**
     * @param bool $all
     * @return Collection<int, Definition>
     */
    public function projects(bool $all = false): Collection
    {
        return collect($this->raw['projects'] ?? [])
            ->map(fn (string $project) => new Definition($project))
            ->filter(fn (Definition $project) => $all || ! in_array($project->repo, $this->settings['disabled']))
            ->map(function (Definition $project): Definition {
                if ($project->repo === $this->projectName()) {
                    throw new UserException('You cannot reference the current project in its own config!');
                }

                return $project;
            })->unique();
    }

    /**
     * @return Collection<string, string>
     */
    public function sites(): Collection
    {
        return collect($this->raw['sites'] ?? []);
    }

    /**
     * @return Collection<string, Command>
     */
    public function commands(): Collection
    {
        return collect($this->raw['commands'] ?? []);
    }

    public function up(): UpConfig
    {
        return $this->up;
    }

    /**
     * @return Steps
     */
    public function steps(): array
    {
        return $this->raw['steps'] ?? [];
    }

    public function path(?string $path = null): string
    {
        return $this->cwd(self::DevDir . DIRECTORY_SEPARATOR . ltrim($path ?? '', DIRECTORY_SEPARATOR));
    }

    public function projectPath(?string $path = null): string
    {
        return $this->cwd(self::DevDir . DIRECTORY_SEPARATOR . self::SrcDir . DIRECTORY_SEPARATOR . self::DefaultSource . DIRECTORY_SEPARATOR . trim($path ?? '', DIRECTORY_SEPARATOR));
    }

    public function devPath(?string $path = null): string
    {
        return $this->cwd(self::DevDir . DIRECTORY_SEPARATOR . trim($path ?? '', DIRECTORY_SEPARATOR));
    }

    public function brewPath(string $path = ''): string
    {
        return match (true) {
            $this->isDarwin()         => join_paths('/opt/homebrew', $path),
            $this->isLinux()          => join_paths('/home/linuxbrew/.linuxbrew', $path),
            default                   => throw new UserException('Encountered unsupported OS ' . php_uname('s') . ' while trying to resolve brew path'),
        };
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
        $home = $this->home() . DIRECTORY_SEPARATOR . self::DevDir;
        if ($path) {
            return $home . DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR);
        }

        return $home;
    }

    public function globalBinPath(?string $path = null): string
    {
        $binPath = 'bin';
        if ($path) {
            $binPath = "bin/$path";
        }

        return $this->globalPath($binPath);
    }

    public static function home(?string $path = null): string
    {
        $home = (string) (getenv('HOME') ?: getenv('USERPROFILE'));
        if (! $path) {
            return $home;
        }

        return join_paths($home, $path);
    }

    public static function sourcePath(?string $path = null, ?string $source = null, ?string $root = null): string
    {
        $sourceDir = sprintf('%s/%s/%s', rtrim($root ?? self::home(), DIRECTORY_SEPARATOR), self::SrcDir, $source ?? self::DefaultSource);

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
        return ! empty($this->raw);
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
        $root = $root ?? sprintf('%s/%s', getcwd(), self::DevDir);

        return static::read(static::sourcePath($path, root: $root), $root);
    }

    /**
     * @throws UserException
     */
    public static function fromProjectDefinition(Definition $project, ?string $root = null): Config
    {
        $root = $root ?? sprintf('%s/%s', getcwd(), self::DevDir);

        return static::read(static::sourcePath($project->repo, root: $root), $root);
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
            $e->setParsedFile(self::fullPath($path));

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
        if (! isset($this->raw['serve'])) {
            return [];
        }

        return $this->raw['serve'];
    }

    public function file(): string
    {
        return $this->cwd(self::FileName);
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
         * to disk. So we can use them next time and not prompt again.
         */
        if ($this->env->wasPrompted()) {
            $this->writeSettings();
        }

        return $resolved;
    }

    public function putenv(string $key, string $value): void
    {
        $this->env->put($key, $value);
    }

    public function isDebug(): bool
    {
        return false;
    }

    /**
     * @return RawConfig|array{}
     */
    public function raw(): array
    {
        return $this->raw;
    }

    public function isDarwin(): bool
    {
        return $this->uname === 'Darwin';
    }

    public function isLinux(): bool
    {
        return $this->uname === 'Linux';
    }

    public function isWindows(): bool
    {
        return $this->uname === 'Windows';
    }

    public function platform(): string
    {
        return $this->uname;
    }
}
