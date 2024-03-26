<?php

namespace App\Plugins\Composer\Config;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugins\Composer\Steps\AuthStep;

class AuthConfig implements Config
{
    public function __construct(private readonly array $auth)
    {
    }

    public function steps(): array
    {
        return collect($this->auth)->map(fn ($site) => $this->makeStep($site))->toArray();
    }

    private function makeStep(array $site): Step
    {
        return new AuthStep(new Auth($site));
    }
}
