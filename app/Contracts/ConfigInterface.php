<?php

namespace App\Contracts;

use App\Step\StepInterface;

interface ConfigInterface
{
    /**
     * @return array<int, StepInterface>
     */
    public function steps(): array;
}
