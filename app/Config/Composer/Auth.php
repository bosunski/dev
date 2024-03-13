<?php

namespace App\Config\Composer;

class Auth
{
    private const AUTH_TYPE_NAME = [
        'basic' => 'http-basic',
    ];

    public readonly string $username;

    public readonly string $host;

    public ?string $password;

    public ?string $token;

    public readonly string $type;

    public function __construct(array $config)
    {
        $this->host = $config['host'] ?? '';
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? null;
        $this->token = $config['token'] ?? null;
        $this->type = $config['type'] ?? 'basic';
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    public function hasToken(): bool
    {
        return $this->token !== null;
    }

    public function isBasic(): bool
    {
        return $this->type === 'basic';
    }

    public function getConfigName(): string
    {
        return self::AUTH_TYPE_NAME[$this->type] . '.' . $this->host;
    }

    public function validate(array $data): bool
    {
        return match ($this->type) {
            'basic' => $this->validateBasic($data),
            default => false,
        };
    }

    private function validateBasic(array $data): bool
    {
        if (! isset($data['username'], $data['password'])) {
            return false;
        }

        return $this->username === $data['username'];
    }
}
