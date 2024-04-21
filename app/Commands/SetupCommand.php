<?php

namespace App\Commands;

use App\Dev;
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

    public function handle(Dev $dev): int
    {
        $this->info('Installing required dependencies...');

        $dev->runner->process('brew install hivemind shadowenv orbstack')
            ->env(['HOMEBREW_NO_AUTO_UPDATE' => '1'])
            ->run(output: $this->handleOutput(...))->throw();

        $this->info('Starting services...');

        return $dev->runner
            ->process(['orb', 'start'])
            ->run(output: $this->handleOutput(...))->successful() ? self::SUCCESS : self::FAILURE;
    }

    protected function handleOutput(string $type, string $buffer): void
    {
        echo $buffer;
    }
}
