<?php

declare(strict_types=1);

namespace App\Plugin\Capability;

use App\Commands\BaseCommand;
use App\Config\Config;

/**
 * @phpstan-import-type Command from Config
 */
interface CommandProvider extends Capability
{
    /**
     * Retrieves an array of commands
     *
     * @return BaseCommand[]
     */
    public function getCommands(): array;

    /**
     * Retrieves an array of commands
     * @return array<string, Command>
     */
    public function getConfigCommands(): array;
}
