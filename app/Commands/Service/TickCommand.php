<?php

namespace App\Commands\Service;

use LaravelZero\Framework\Commands\Command;
use Phar;
use Swoole\Timer;

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
        echo phpinfo();
        var_dump(php_sapi_name(), Phar::running());
        Timer::after(1000, function (): void {
            $this->info('tick');
        });
    }
}
