<?php

namespace App\Commands;

use App\Config\Config;
use App\Execution\Runner;
use App\Step\CdStep;
use App\Step\Git\CloneStep;
use Exception;
use LaravelZero\Framework\Commands\Command;

class CloneCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'clone {repo}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Clones a GitHub repository';

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

        return $runner->execute([
            new CloneStep(...CloneStep::parseService($repo = $this->argument('repo'))),
            new CdStep($repo)
        ]);
    }
}
