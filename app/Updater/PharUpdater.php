<?php

namespace App\Updater;

use Humbug\SelfUpdate\Updater as BaseUpdater;
use Phar;
use RuntimeException;

class PharUpdater extends BaseUpdater
{
    protected bool $dryRun = false;

    protected ?string $tag = null;

    /**
     * @param string $phar
     * @return void
     */
    protected function validatePhar($phar): void
    {
        // The default validatePhar implmentation doesn't apply to our case so we override it
    }

    protected function backupPhar(): void
    {
        if ($this->dryRun) {
            return;
        }

        parent::backupPhar();
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

        chmod($this->getTempPharFile(), $currentMode);

        if ($this->dryRun) {
            return;
        }

        rename($this->getTempPharFile(), $this->getLocalPharFile());
    }

    public function dryRun(bool $dryRun = true): static
    {
        $this->dryRun = $dryRun;

        return $this;
    }

    public function setTag(string $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    protected function setTempDirectory(): void
    {
        if (Phar::running()) {
            parent::setTempDirectory();

            return;
        }

        $this->tempDirectory = getcwd() === false
            ? throw new RuntimeException('Unable to retrieve the current working directory!')
            : getcwd() . '/dist';
    }
}
