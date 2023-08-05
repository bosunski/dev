<?php

namespace App\Config;

use App\Contracts\ConfigInterface;
use App\Step\StepInterface;

abstract class BaseConfig implements ConfigInterface
{
    public function steps(): array
    {
        $steps = [];
        foreach ($this->steps as $step) {
            foreach ($step as $name => $args) {
                $configOrStep = $this->makeStep($name, $args);

                if ($configOrStep instanceof ConfigInterface) {
                    $steps = [...$steps, ...$configOrStep->steps()];
                    continue;
                }

                $steps[] = $configOrStep;
            }
        }

        return $steps;
    }
}
