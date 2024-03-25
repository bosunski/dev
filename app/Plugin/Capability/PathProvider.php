<?php

declare(strict_types=1);

namespace App\Plugin\Capability;

interface PathProvider extends Capability
{
    /**
     * Retrieves an array of $PATH values
     *
     * @return string[]
     */
    public function paths(): array;
}
