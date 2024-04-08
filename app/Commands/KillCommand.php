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
    protected $signature = 'kill {--service=}';

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

        if ($service = $this->option('service')) {
            if ($config->projects()->contains($service)) {
                $config = Config::fromProjectName($service);
            } else {
                throw new UserException("Service $service not found in this project. Are you sure it is registered?");
            }
        }

        if (! file_exists($config->path('dev.pid'))) {
            $this->error('No running services found');

            return self::FAILURE;
        }

        $pid = file_get_contents($config->path('dev.pid'));

        if (! posix_kill($pid, SIGTERM)) {
            $this->error('Failed to kill services');

            return self::FAILURE;
        }

        $this->info('Killed services');

        return self::SUCCESS;
    }
}
