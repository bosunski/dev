<?php

declare(strict_types=1);

namespace App\Plugin\Capability;

interface EnvProvider extends Capability
{
    /**
     * Retrieves an array of commands
     *
     * @return array<string, string>
     */
    public function envs(): array;
}
