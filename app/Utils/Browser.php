<?php

namespace App\Utils;

class Browser
{
    public static function open(string $url): void
    {
        self::runCommand(sprintf('%s %s', self::getSystemCommand(), $url));
    }

    private static function getSystemCommand(): string
    {
        return match (PHP_OS) {
            'Darwin' => 'open',
            'WINNT'  => 'start',
            default  => 'xdg-open',
        };
    }

    private static function runCommand($command): void
    {
        shell_exec($command);
    }
}
