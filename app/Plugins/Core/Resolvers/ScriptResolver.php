<?php

namespace App\Plugins\Core\Resolvers;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;
use App\Plugins\Core\Steps\CustomStep;
use InvalidArgumentException;

class ScriptResolver implements StepResolverInterface
{
    public function resolve(mixed $args): Config|Step
    {
        if (! is_array($args)) {
            throw new InvalidArgumentException('Script configuration should be an array!');
        }

        return new CustomStep($args);
    }
}
