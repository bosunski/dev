<?php

namespace App\Commands;

use App\Exceptions\UserException;
use LaravelZero\Framework\Commands\Command;

class InitCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'init {shell=zsh}';

    protected $description = 'Initializes preeexec hook';

    public function handle(): int
    {
        if ($this->argument('shell') !== 'zsh') {
            throw new UserException('Only ZSH is supported');
        }

        echo view('init.dev-zsh', [
            'self' => '/usr/local/bin/dev',
        ]);

        return 0;
    }
}
