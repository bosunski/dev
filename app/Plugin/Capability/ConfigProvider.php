<?php

declare(strict_types=1);

namespace App\Plugin\Capability;

use App\Plugin\StepResolverInterface;
use App\Step\StepInterface;

interface ConfigProvider extends Capability
{
    /**
     * Retrieves an array of commands
     *
     * @return StepInterface[]
     */
    public function steps(): array;

    public function validate(): bool;

    /**
     * Retrieves an array of step resolvers
     * @return StepResolverInterface[]
     */
    public function stepResolvers(): array;
}
