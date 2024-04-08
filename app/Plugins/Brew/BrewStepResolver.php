<?php

namespace App\Plugins\Brew;

use App\Dev;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;
use App\Plugins\Brew\Steps\BrewStep;

class BrewStepResolver implements StepResolverInterface
{
    public function __construct(protected readonly Dev $dev)
    {
    }

    /**
     * @param mixed $args
     * @return Config|Step
     */
    public function resolve(mixed $args): Config | Step
    {
        return new BrewStep($args);
    }
}
