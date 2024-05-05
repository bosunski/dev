<?php

namespace App\Commands;

use App\Dev;
use App\Step\ServeStep;
use LaravelZero\Framework\Commands\Command;

class ServeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'serve';

    /**
     * @var string[]
     */
    protected $aliases = ['s'];

    /**
     * @var string
     */
    protected $description = 'Start the application services';

    public function handle(Dev $dev): int
    {
        return (int) (new ServeStep($dev))->run();
    }
}
