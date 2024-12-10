<?php

namespace App\Plugins\Spc;

use App\Dev;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;
use App\Plugins\Spc\Config\SpcConfig;

/**
 * @phpstan-import-type RawSpcConfig from SpcConfig
 */
class SpcStepResolver implements StepResolverInterface
{
    public function __construct(protected readonly Dev $dev)
    {
    }

    /**
     * @param RawSpcConfig $args
     * @return Config|Step
     */
    public function resolve(mixed $args): Config | Step
    {
        assert(is_array($args), 'Spc configuration should be an array');

        return new SpcConfig($args, $this->dev->config);
    }
}
