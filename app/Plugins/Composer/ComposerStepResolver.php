<?php

namespace App\Plugins\Composer;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;
use App\Plugins\Composer\Config\ComposerConfig;

class ComposerStepResolver implements StepResolverInterface
{
    /**
     * @param mixed $args
     * @return Config|Step
     */
    public function resolve(mixed $args): Config | Step
    {
        assert(is_array($args), 'Composer configuration should be an array');

        return new ComposerConfig($args);
    }
}
