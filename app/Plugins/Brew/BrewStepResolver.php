<?php

namespace App\Plugins\Brew;

use App\Dev;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;
use App\Plugins\Brew\Steps\BrewStep;
use InvalidArgumentException;

class BrewStepResolver implements StepResolverInterface
{
    public function __construct(protected readonly Dev $dev)
    {
    }

    /**
     * @param string[] $args
     * @return Config|Step
     */
    public function resolve(mixed $args): Config | Step
    {
        if (! is_array($args)) {
            throw new InvalidArgumentException('Brew configuration should be an array!');
        }

        return new BrewStep($args);
    }
}
