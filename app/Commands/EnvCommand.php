<?php

namespace App\Commands;

use App\Exceptions\UserException;
use LaravelZero\Framework\Commands\Command;

/**
 * This command is based on ShadowEnv's hook command and Hookbook
 * @see https://github.com/Shopify/shadowenv/blob/main/src/hook.rs
 */
class EnvCommand extends Command
{
    protected const SupportedShells = ['zsh', 'bash', 'fish'];

    /**
     * @var string
     */
    protected $signature = 'env {shell=zsh : The shell to initialize the hook for. Supported shells are: zsh, bash, fish}';

    protected $description = 'Initializes preeexec hook for BASH, FISH or ZSH';

    public function handle(): int
    {
        $shell = $this->argument('shell');
        if (! in_array($shell, self::SupportedShells)) {
            throw new UserException('Unsupported shell. Supported shells are: ' . implode(', ', self::SupportedShells));
        }

        $self = $_SERVER['PHP_SELF'];
        if (! is_string($self) || ! is_file($self)) {
            throw new UserException('Could not determine the path to the DEV executable.');
        }

        echo view("env.$shell", ['self' => $self]);

        return 0;
    }
}
