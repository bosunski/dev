<?php

namespace App\Config;

use App\Exceptions\UserException;
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

    public function services(): array
    {
        return $this->config['services'] ?? [];
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

    public function path(): string
    {
        return $this->cwd(self::OP_PATH);
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
