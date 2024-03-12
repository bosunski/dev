<?php

namespace App\Updater;

class PharUpdater extends \Humbug\SelfUpdate\Updater
{
    protected function validatePhar($phar)
    {
        dump('Validating Phar');

        // Check if the Phar is running
    }
}