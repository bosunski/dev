<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class InstallCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'install';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Put the application in system binary path';

    public function handle(): int
    {
        $this->info("Building Garm...");
        Process::forever()->tty()->run("php garm app:build --no-interaction", function (string $type, string $output) {
            echo $output;
        })->throw();

        $this->info("Installing Garm...");

        Process::forever()->tty()->run("sudo cp ./builds/garm /usr/local/bin/garm", function (string $type, string $output) {
            echo $output;
        })->throw();

        $this->info("Garm installed successfully!");

        return 0;
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
