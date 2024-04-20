<?php

namespace App\Plugins\Composer\Config;

use App\Utils\Value;

/**
 * @phpstan-import-type PromptArgs from Value
 * @phpstan-type RawAuth array{
 *      host: string,
 *      username?: string,
 *      password?: string|PromptArgs,
 *      token?: string|PromptArgs,
 *      type?: 'basic'
 * }
 */
class Auth
{
    private const AUTH_TYPE_NAME = [
        'basic' => 'http-basic',
    ];

    public readonly string $username;

    public readonly string $host;

    /**
     * @var string|PromptArgs
     */
    public string|array $password;

    /**
     * @var string|PromptArgs
     */
    public string|array $token;

    public readonly string $type;

    /**
     * @param RawAuth $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->host = $config['host'];
        $this->username = $config['username'] ?? '';
        $hint = "This will be used by composer to authenticate {$this->host} and stored in the global composer config file.";
        $this->password = $config['password'] ?? [
            'prompt'   => 'password',
            'label'    => "Enter password for {$this->host}",
            'hint'     => $hint,
            'required' => true,
        ];

        $this->token = $config['token'] ?? [
            'prompt'   => 'token',
            'label'    => "Enter token for {$this->host}",
            'hint'     => $hint,
            'required' => true,
        ];

        $this->type = $config['type'] ?? 'basic';
    }

    public function hasPassword(): bool
    {
        return is_string($this->password);
    }

    public function hasToken(): bool
    {
        return is_string($this->token);
    }

    public function isBasic(): bool
    {
        return $this->type === 'basic';
    }

    public function getConfigName(): string
    {
        return self::AUTH_TYPE_NAME[$this->type] . '.' . $this->host;
    }
}
