<?php

namespace App\Commands;

use App\Config\Config;
use App\Execution\Runner;
use App\Step\ServeStep;
use App\Step\UpStep;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ServeCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'serve';

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
     */
    public function handle()
    {
        $config = new Config(getcwd(), []);
        $runner = new Runner($config, $this);

        return $runner->execute([new ServeStep($config->cwd())]);
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
