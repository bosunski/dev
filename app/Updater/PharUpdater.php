<?php

namespace App\Updater;

use Humbug\SelfUpdate\Updater as BaseUpdater;

class PharUpdater extends BaseUpdater
{
    protected function validatePhar($phar): void
    {
        // The default validatePhar implmentation doesn't apply to our case so we override it
    }

    protected function replacePhar(): void
    {
        /**
         * We will get the current mode of the binary file and apply it to the new Phar file
         * after we have moved it to the location of the old Phar file.
         */
        $currentMode = fileperms($this->getLocalPharFile());
        rename($this->getTempPharFile(), $this->getLocalPharFile());
        chmod($this->getLocalPharFile(), $currentMode);
    }
}
