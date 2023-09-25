<?php

namespace App\Commands;

use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class SetupCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'setup';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Put the application in system binary path';

    public function handle(): int
    {
        $this->info("Installing required dependencies...");
        Process::forever()
            ->tty()
            ->run("brew install hivemind shadowenv php", function (string $type, string $output) {
                echo $output;
            })->throw();

        return 0;
    }
}
