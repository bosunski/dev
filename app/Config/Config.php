<?php

namespace App\Config;

use App\Exceptions\UserException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Config
{
    public const OP_PATH = '.garm';

    private const REPO_LOCATION = "src";

    private const DEFAULT_SOURCE_HOST = "github.com";

    public const FILE_NAME = "garm.yaml";

    public function __construct(protected string $path, protected readonly array $config, public readonly bool $isRoot = false)
    {
    }

    public function getName(): string
    {
        return $this->config['name'] ?? '';
    }

    public function services(): Collection
    {
        return collect($this->config['services'] ?? [])->map(function (string $service) {
            if ($service === $this->serviceName()) {
                throw new UserException("You cannot reference the current service in its own config!");
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

    public function service(): Service
    {
        return new Service($this);
    }

    public function getType(): string
    {
        return $this->config['type'] ?? '';
    }

    public function getPhp(): string
    {
        return $this->config['php'] ?? PHP_VERSION;
    }

    public function up(): UpConfig
    {
        return new UpConfig($this);
    }

    public function steps(): array
    {
        return $this->config['up'] ?? [];
    }

    public function path(?string $path = null): string
    {
        return $this->cwd(self::OP_PATH . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR));
    }

    public function cwd(?string $path = null): string
    {
        if ($path) {
            return $this->path . '/' . $path;
        }

        return $this->path;
    }

    public static function home(): string
    {
        return getenv('HOME');
    }

    public static function sourcePath(?string $path = null): string
    {
        $sourceDir = sprintf("%s/%s/%s", self::home(), self::REPO_LOCATION, self::DEFAULT_SOURCE_HOST);

        if ($path) {
            return $sourceDir . '/' . ltrim($path, '/');
        }

        return $sourceDir;
    }

    public function serviceName(): string
    {
        return Str::of($this->cwd())->after($this->sourcePath())->trim('/')->toString();
    }

    public function isAGarmProject(): bool
    {
        return !empty($this->config);
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
    public static function fromServiceName(string $path): Config
    {
        return static::read(static::sourcePath($path));
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
        return $path . '/' . self::FILE_NAME;
    }
}
