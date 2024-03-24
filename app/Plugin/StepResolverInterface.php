<?php

namespace App\Plugin;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;

interface StepResolverInterface
{
    /**
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * @param mixed $args
     * @return Config|Step[]
     */
    public function resolve(mixed $args): Config | array;
}
