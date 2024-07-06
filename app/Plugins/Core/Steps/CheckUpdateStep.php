<?php

namespace App\Plugins\Core\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Updater\PharUpdater;
use App\Updater\Updater;

class CheckUpdateStep implements Step
{
    public function name(): ?string
    {
        return 'Check for DEV updates';
    }

    public function run(Runner $runner): bool
    {
        /** @var PharUpdater $updater */
        $updater = app(Updater::class)->updater;
        if ($updater->hasUpdate()) {
            $runner->io()->dev("New version of DEV is available: {$updater->getNewVersion()}. Run `dev upgrade` to update.");
        }

        return true;
    }

    public function done(Runner $runner): bool
    {
        return false;
    }

    public function id(): string
    {
        return 'dev.update.check';
    }
}
