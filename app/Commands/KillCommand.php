<?php

namespace App\Commands;

use App\Config\Config;
use App\Dev;
use App\Exceptions\UserException;
use LaravelZero\Framework\Commands\Command;

class KillCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'kill {--project=}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Kill the running application services';

    /**
     * @throws UserException
     */
    public function handle(Dev $dev): int
    {
        $config = $dev->config;
        if ($project = $this->option('project')) {
            if ($config->projects()->contains($project)) {
                $config = Config::fromProjectName($project);
            } else {
                throw new UserException("Service $project not found in this project. Are you sure it is registered?");
            }
        }

        if (! file_exists($config->path('dev.pid'))) {
            $this->error('No running services found');

            return self::FAILURE;
        }

        $pid = file_get_contents($config->path('dev.pid'));

        if ($pid === false) {
            $this->error('Failed to read services PID');

            return self::FAILURE;
        }

        if (! posix_kill((int) $pid, SIGTERM)) {
            $this->error('Failed to kill services');

            return self::FAILURE;
        }

        $this->info('Killed services');

        return self::SUCCESS;
    }
}
