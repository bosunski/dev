<?php

namespace App\Commands\Service;

use LaravelZero\Framework\Commands\Command;
use Phar;

class TickCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'app:tick';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        var_dump(php_sapi_name(), Phar::running());
        swoole_timer_tick(1000, function (): void {
            $this->info('tick');
        });
    }
}
