<?php

namespace App\Step;

use App\Execution\Runner;

class PHPStep implements StepInterface
{
    public const DEFAULT_EXTENSIONS = [
        'bcmath',
        'calendar',
        'ctype',
        'curl',
        'dba',
        'dom',
        'exif',
        'filter',
        'fileinfo',
        'iconv',
        'mbstring',
        'mbregex',
        'openssl',
        'pcntl',
        'pdo',
        'pdo_mysql',
        'pdo_sqlite',
        'phar',
        'posix',
        'readline',
        'simplexml',
        'sockets',
        'sqlite3',
        'tokenizer',
        'xml',
        'xmlreader',
        'xmlwriter',
        'zip',
        'zlib',
        'sodium',
    ];

    public function __construct(protected readonly array $config)
    {
    }

    public function name(): ?string
    {
        return null;
    }

    public function run(Runner $runner): bool
    {
        $spc = $runner->config()->devPath('bin/spc');
        $extensions = $this->config['extensions'];
        dump($this->config);

        return true;
    }

    public function done(Runner $runner): bool
    {
        return false;
    }

    public function id(): string
    {
        return 'php';
    }
}
