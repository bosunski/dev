<?php

namespace App\Commands;

use App\Dev;
use App\Step\ServeStep;
use Exception;
use LaravelZero\Framework\Commands\Command;
use Throwable;

class ServeCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'serve';

    /**
     * @var string[]
     */
    protected $aliases = ['s'];

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Start the application services';

    /**
     * Execute the console command.
     * @throws Exception
     */
    public function handle(Dev $dev): int
    {
        try {
            $serveStep = new ServeStep($dev);

            return $dev->runner->execute([$serveStep], true);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return 1;
        }
    }
}
