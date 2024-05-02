<?php

namespace App\Plugin;

use App\Plugin\Capability\Capability;

interface Capable
{
    /**
     * @return array<class-string<Capability>, class-string>
     */
    public function capabilities(): array;
}
