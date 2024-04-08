<?php

namespace App\Plugins\Composer;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;
use App\Plugins\Brew\Steps\BrewStep;

class ComposerStepResolver implements StepResolverInterface
{
    /**
     * @param mixed $args
     * @return Config|Step
     */
    public function resolve(mixed $args): Config | Step
    {
        return new BrewStep($args);
    }
}
