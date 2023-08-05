<?php

namespace App\Config\Composer;

use App\Contracts\ConfigInterface;
use App\Step\Composer\AuthStep;
use App\Step\StepInterface;

class AuthConfig implements ConfigInterface
{
    public function __construct(private readonly array $auth)
    {
    }

    public function steps(): array
    {
        return collect($this->auth)->map(fn ($site) => $this->makeStep($site))->toArray();
    }

    private function makeStep(array $site): StepInterface
    {
        return new AuthStep(new Auth($site));
    }
}
