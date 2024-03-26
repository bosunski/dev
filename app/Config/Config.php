<?php

namespace App\Config;

use App\Exceptions\UserException;
use App\Plugin\StepResolverInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
 */
class Config
{
    public const OP_PATH = '.dev';

    private const REPO_LOCATION = 'src';

    private const DEFAULT_SOURCE_HOST = 'github.com';

    public const FILE_NAME = 'dev.yml';

    public readonly Collection $paths;

    public array $settings = [];

    private readonly UpConfig $up;

    public function __construct(protected string $path, protected readonly array $config, public bool $isRoot = false)
    {
        $this->readSettings();

        $this->paths = collect();
        $this->up = new UpConfig($this);
    }

    public function addStepResolver(StepResolverInterface $resolver): void
    {
        $this->up->addResolver($resolver);
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

    public function services(bool $all = false): Collection
    {
        return collect($this->config['services'] ?? [])->filter(fn ($service) => $all || ! in_array($service, $this->settings['disabled']))->map(function (string $service) {
            if ($service === $this->serviceName()) {
                throw new UserException('You cannot reference the current service in its own config!');
            }

            return $service;
        })->unique();
    }

    public function sites(): Collection
    {
        return collect($this->config['sites'] ?? []);
    }

    public function commands(): Collection
    {
        return collect($this->config['commands'] ?? []);
    }

    public function getType(): string
    {
        return $this->config['type'] ?? '';
    }

    public function up(): UpConfig
    {
        return $this->up;
    }

    public function steps(): array
    {
        return $this->config['up'] ?? [];
    }

    public function valet(): array
    {
        return $this->steps()['valet'] ?? [];
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

    public static function home(): string
    {
        return getenv('HOME');
    }

    public static function sourcePath(?string $path = null, ?string $source = null, ?string $root = null): string
    {
        $sourceDir = sprintf('%s/%s/%s', rtrim($root ?? self::home(), DIRECTORY_SEPARATOR), self::REPO_LOCATION, $source ?? self::DEFAULT_SOURCE_HOST);

        if ($path) {
            return $sourceDir . DIRECTORY_SEPARATOR . ltrim($path, '/');
        }

        return $sourceDir;
    }

    public function serviceName(): string
    {
        return Str::of($this->cwd())->after($this->sourcePath())->trim('/')->toString();
    }

    public function isDevProject(): bool
    {
        return ! empty($this->config);
    }

    /**
     * @throws UserException
     */
    public static function read(string $path): Config
    {
        return new Config($path, self::parseYaml($path));
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
    public static function fromServiceName(string $path, ?string $root = null): Config
    {
        $root = $root ?? sprintf('%s/%s', getcwd(), self::OP_PATH);

        return static::read(static::sourcePath($path, root: $root));
    }

    /**
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
        return $path . DIRECTORY_SEPARATOR . self::FILE_NAME;
    }

    public function getServe(): array
    {
        return $this->config['serve'] ?? [];
    }

    public function envs(): Collection
    {
        return collect($this->config['env'] ?? []);
    }
}
