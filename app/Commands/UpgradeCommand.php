<?php

namespace App\Commands;

use App\Updater\Updater;
use Illuminate\Support\Env;
use LaravelZero\Framework\Commands\Command;
use Phar;

class UpgradeCommand extends Command
{
    protected $name = 'upgrade {version? : The version to upgrade to. Only tag names are supported.}';

    protected $description = 'Allows to self-update a build application';

    public function handle(Updater $updater): int
    {
        if (! Phar::running()) {
            $this->error('This command is only available in PHAR builds.');

            return self::FAILURE;
        }

        if (! env('GITHUB_TOKEN')) {
            $token = $this->ask('Cannot find GITHUB_TOKEN env. Please provide a GitHub token');
            if (! $token) {
                $this->error('GitHub token is required to check for updates.');

                return self::INVALID;
            }

            assert(is_string($token), 'Token must be a string');
            Env::getRepository()->set('GITHUB_TOKEN', $token);
        }

        $this->output->title('Checking for a new version...');
        $result = $updater->updater->update();

        if ($result) {
            $this->output->success(sprintf(
                'Updated from version %s to %s.',
                $updater->updater->getOldVersion(),
                $updater->updater->getNewVersion()
            ));

            return self::SUCCESS;
        }

        if (! $updater->updater->getNewVersion()) {
            $this->output->success('There are no stable versions available.');
        } else {
            $this->output->success('You have the latest version installed.');
        }

        return self::SUCCESS;
    }
}
