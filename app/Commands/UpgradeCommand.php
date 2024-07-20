<?php

namespace App\Commands;

use App\Updater\PharUpdater;
use App\Updater\Updater;
use LaravelZero\Framework\Commands\Command;
use Phar;

class UpgradeCommand extends Command
{
    protected $signature = 'upgrade {version? : The version to upgrade to. Only tag names are supported.}
                            {--dry-run : Perform a dry run}';

    protected $description = 'Upgrade the application to the latest version or to a specific version';

    protected PharUpdater $updater;

    public function __construct(Updater $updater)
    {
        parent::__construct();

        $this->updater = $updater->updater;
    }

    public function handle(): int
    {
        $dryRun = ! Phar::running() || $this->option('dry-run');
        if ($dryRun) {
            $this->components->warn('Running upgrade in dry-run mode.');
        }

        $this->updater->dryRun($dryRun);
        if ($this->argument('version')) {
            $this->updater->setTag($this->argument('version'));
        }

        $this->components->info('Checking for a new version...');
        $result = $this->updater->update();

        [$oldVersion, $newVersion] = [$this->updater->getOldVersion(), $this->updater->getNewVersion()];
        $isUpgrade = $this->isUpgrade($oldVersion, $newVersion);

        $action = $isUpgrade ? 'Upgraded' : 'Downgraded';

        if ($result) {
            $this->components->info("$action from version $oldVersion to $newVersion.");

            return self::SUCCESS;
        }

        if (! $this->updater->getNewVersion()) {
            $this->output->success('There are no stable versions available.');
        } else {
            $this->output->success('You have the latest version installed.');
        }

        return self::SUCCESS;
    }

    protected function isUpgrade(string $currentVersion, string $newVersion): bool
    {
        return version_compare($currentVersion, $newVersion, '<');
    }
}
