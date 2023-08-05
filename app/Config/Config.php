<?php

namespace App\Config;

class Config
{
    public const OP_PATH = '.garm';

    public function __construct(protected readonly array $config)
    {
    }

    public function getName(): string
    {
        return $this->config['name'] ?? '';
    }

    public function getType(): string
    {
        return $this->config['type'] ?? '';
    }

    public function getPhp(): float
    {
        return (float) $this->config['php'] ?? PHP_VERSION;
    }

    public function up(): UpConfig
    {
        return new UpConfig($this->config['up'] ?? []);
    }

    public function path(): string
    {
        return $this->cwd(self::OP_PATH);
    }

    public function cwd(?string $path = null): string
    {
        if ($path) {
            return getcwd() . '/' . $path;
        }

        return getcwd();
    }
}
