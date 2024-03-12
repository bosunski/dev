<?php

namespace App\Commands;

use App\Updater\Updater;
use LaravelZero\Framework\Commands\Command;
use Phar;

class SelfUpdateCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $name = 'self-update';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Allows to self-update a build application';

    /**
     * {@inheritdoc}
     */
    public function handle(Updater $updater)
    {
        if (! Phar::running()) {
            $this->error('This command is only available in PHAR builds.');
            return 1;
        }

        $this->output->title('Checking for a new version...');
        $updater->update($this->output);
    }
}
