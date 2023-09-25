<?php

namespace App\Commands;

use App\Config\Config;
use App\Exceptions\UserException;
use LaravelZero\Framework\Commands\Command;

class KillCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'kill';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Kill the running application services';

    /**
     * @throws UserException
     */
    public function handle(): int
    {
        $config = Config::fromPath(getcwd());

        if (! file_exists($config->path('garm.pid'))) {
            $this->error('No running services found');
            return self::FAILURE;
        }

        $pid = file_get_contents($config->path('garm.pid'));

        if (! posix_kill($pid, SIGTERM)) {
            $this->error('Failed to kill services');
            return self::FAILURE;
        }

        $this->info('Killed services');

        return self::SUCCESS;
    }
}