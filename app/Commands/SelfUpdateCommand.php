<?php

namespace App\Commands;

use App\Updater\Updater;
use Illuminate\Support\Env;
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

        if (! env('GITHUB_TOKEN')) {
            $token = $this->ask('Cannot find GITHUB_TOKEN env. Please provide a GitHub token');
            if (! $token) {
                $this->error('GitHub token is required to check for updates.');
                return 1;
            }

            Env::getRepository()->set('GITHUB_TOKEN', $token);
        }

        $this->output->title('Checking for a new version...');
        $updater->update($this->output);
    }
}
