<?php

namespace App\Commands;

use App\Config\Config;
use App\Dev;
use App\Exceptions\UserException;
use LaravelZero\Framework\Commands\Command;

class InitCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'init {path? : The path to the project root}';

    /**
     * @var string
     */
    protected $description = 'Create a new dev.yml file in the project root';

    public function handle(Dev $dev): int
    {
        $config = $dev->config;
        if ($this->argument('path')) {
            $config = Config::fromPath($this->argument('path'));
        }

        if (is_file($config->file())) {
            throw new UserException('DEV is already initialized for this project. See the dev.yml file in the project root.');
        }

        if (! file_put_contents($config->file(), view('init.yaml')->render())) {
            throw new UserException('Could not create the dev.yml file.');
        }

        $this->components->info("Initialized DEV at {$config->file()}");

        return self::SUCCESS;
    }
}
