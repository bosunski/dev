<?php

namespace App\Plugins\Core\Resolvers;

use App\Config\Config as DevConfig;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;
use App\Plugins\Core\Steps\CustomStep;
use InvalidArgumentException;

/**
 * @phpstan-import-type Script from DevConfig
 */
class ScriptResolver implements StepResolverInterface
{
    /**
     * @param Script $args
     * @return Config|Step
     * @throws InvalidArgumentException
     */
    public function resolve(mixed $args): Config|Step
    {
        if (! is_array($args)) {
            throw new InvalidArgumentException('Script configuration should be an array!');
        }

        return new CustomStep($args);
    }
}
