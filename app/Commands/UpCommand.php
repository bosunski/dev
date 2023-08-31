<?php

namespace App\Commands;

use App\Config\Config;
use App\Execution\Runner;
use App\Step\UpStep;
use Exception;
use LaravelZero\Framework\Commands\Command;

class UpCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'up';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Boostrap a project';

    /**
     * @throws Exception
     */
    public function handle(): int
    {
        $config = new Config(getcwd(), []);
        $runner = new Runner($config, $this);

        return $runner->execute([new UpStep($config->cwd())]);
    }
}
