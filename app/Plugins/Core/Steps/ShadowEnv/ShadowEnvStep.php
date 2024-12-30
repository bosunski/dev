<?php

namespace App\Plugins\Core\Steps\ShadowEnv;

use App\Dev;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;

class ShadowEnvStep implements Step
{
    public function __construct(protected readonly Dev $dev)
    {
    }

    public function name(): string
    {
        return 'Initialize Shadowenv';
    }

    public function run(Runner $runner): bool
    {
        if (! $runner->config()->isDevProject()) {
            return true;
        }

        return $this->dev->updateEnvironment();
    }

    public function done(Runner $runner): bool
    {
        return $this->run($runner);
    }

    public function id(): string
    {
        return "shadowenv-{$this->dev->config->path()}";
    }
}
