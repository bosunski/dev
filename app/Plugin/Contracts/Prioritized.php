<?php

namespace App\Plugin\Contracts;

use App\Plugin\Priority;

interface Prioritized
{
    public function priority(): Priority;
}
