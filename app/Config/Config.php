<?php

namespace App\Config;

use Illuminate\Support\Str;

class Config
{
    public const OP_PATH = '.garm';

    private const REPO_LOCATION = "src";

    private const DEFAULT_SOURCE_HOST = "github.com";

    public function __construct(protected string $path, protected readonly array $config)
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

    public function home(): string
    {
        return getenv('HOME');
    }

    public function sourcePath(?string $path = null): string
    {
        $sourceDir = sprintf("%s/%s/%s", $this->home(), self::REPO_LOCATION, self::DEFAULT_SOURCE_HOST);

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
}
