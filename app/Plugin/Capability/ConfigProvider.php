<?php

declare(strict_types=1);

namespace App\Plugin\Capability;

use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;

interface ConfigProvider extends Capability
{
    /**
     * Retrieves an array of steps that the plugin wants to add
     * before user defined steps are going to run.
     *
     * @return Step[]
     */
    public function steps(): array;

    /**
     * Allows a plugin to validate the Dev configuration. This is useful
     * since plugins might provide functionalities based on a certain
     * configuration that is provieded by the user.
     *
     * @return bool
     */
    public function validate(): bool;

    /**
     * Retrieves an array of step resolvers that will resolve steps
     * based on the configurations nested under the `up`. This is a map
     * of the name of the config and the resolver that will receive the
     * config and yield the steps.
     *
     * @return array<non-empty-string, StepResolverInterface>
     */
    public function stepResolvers(): array;
}
