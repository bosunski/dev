<?php

namespace App\Commands;

use App\Config\Config;
use App\Execution\Runner;
use App\Step\ServeStep;
use Exception;
use LaravelZero\Framework\Commands\Command;

class ServeCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'serve';

    protected $aliases = ['s'];

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Start the application services';

    /**
     * Execute the console command.
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function handle(): int
    {
        $config = new Config(getcwd(), []);
        $runner = new Runner($config, $this);

        return $runner->execute([new ServeStep($config->cwd())]);
    }
}
