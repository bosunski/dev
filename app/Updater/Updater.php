<?php

namespace App\Updater;

use Illuminate\Console\OutputStyle;

class Updater
{
    /**
     * The base updater.
     *
     * @var \Humbug\SelfUpdate\Updater
     */
    public $updater;

    /**
     * Updater constructor.
     */
    public function __construct(PharUpdater $updater)
    {
        $this->updater = $updater;
    }

    public function update(OutputStyle $output): void
    {
        $result = $this->updater->update();

        if ($result) {
            $output->success(sprintf(
                'Updated from version %s to %s.',
                $this->updater->getOldVersion(),
                $this->updater->getNewVersion()
            ));

            exit(0);
        }

        if (! $this->updater->getNewVersion()) {
            $output->success('There are no stable versions available.');
        } else {
            $output->success('You have the latest version installed.');
        }
    }
}
