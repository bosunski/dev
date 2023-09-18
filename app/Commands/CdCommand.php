<?php

namespace App\Commands;

use App\Config\Config;
use App\Execution\Runner;
use App\Step\CdStep;
use Exception;
use LaravelZero\Framework\Commands\Command;

class CdCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'cd {repo}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Change directory to a project repo';

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws Exception
     */
    public function handle(): int
    {
        $config = new Config(getcwd(), []);
        $runner = new Runner($config, $this);

        return $runner->execute([new CdStep($this->argument('repo'))]);
    }
}
