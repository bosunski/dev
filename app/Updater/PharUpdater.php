<?php

namespace App\Updater;

use Humbug\SelfUpdate\Updater as BaseUpdater;
use RuntimeException;

class PharUpdater extends BaseUpdater
{
    /**
     * @param string $phar
     * @return void
     */
    protected function validatePhar(string $phar): void
    {
        // The default validatePhar implmentation doesn't apply to our case so we override it
    }

    protected function replacePhar(): void
    {
        /**
         * We will get the current mode of the binary file and apply it to the new Phar file
         * after we have moved it to the location of the old Phar file.
         */
        $currentMode = fileperms($currentPharPath = $this->getLocalPharFile());
        if ($currentMode === false) {
            throw new RuntimeException("Unable to get the current mode of the Phar file: $currentPharPath");
        }

        rename($this->getTempPharFile(), $this->getLocalPharFile());
        chmod($this->getLocalPharFile(), $currentMode);
    }
}
