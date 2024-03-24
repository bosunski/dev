<?php

namespace App\Plugin;

use App\Plugin\Capability\Capabilities;

interface Capable
{
    /**
     * @return array<value-of<Capabilities>, string>
     */
    public function capabilities(): array;
}
