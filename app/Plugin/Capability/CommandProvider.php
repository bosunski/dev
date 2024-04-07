<?php

declare(strict_types=1);

namespace App\Plugin\Capability;

use App\Config\Config;
use Illuminate\Console\Command;

/**
 * @phpstan-import-type Command from Config as ConfigCommand
 */
interface CommandProvider extends Capability
{
    /**
     * Retrieves an array of commands
     *
     * @return Command[]
     */
    public function getCommands(): array;

    /**
     * Retrieves an array of commands
     * @return array<string, ConfigCommand>
     */
    public function getConfigCommands(): array;
}
