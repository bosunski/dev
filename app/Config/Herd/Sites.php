<?php

namespace App\Config\Herd;

use App\Contracts\ConfigInterface;
use App\Step\Herd\SiteStep;
use App\Step\StepInterface;

class Sites implements ConfigInterface
{
    public function __construct(private readonly array $sites)
    {
    }

    public function steps(): array
    {
        return collect($this->sites)->map(fn ($site) => $this->makeStep($site))->toArray();
    }

    private function makeStep(array|string $site): StepInterface
    {
        return new SiteStep(new Site($site));
    }
}
