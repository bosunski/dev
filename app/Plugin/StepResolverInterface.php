<?php

namespace App\Plugin;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;

interface StepResolverInterface
{
    /**
     * @param mixed $args
     * @return Config|Step
     */
    public function resolve($args): Config | Step;
}
