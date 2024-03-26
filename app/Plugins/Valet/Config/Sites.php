<?php

namespace App\Plugins\Valet\Config;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Steps\SiteStep;

class Sites implements Config
{
    /**
     * @param  array<int,mixed>  $sites
     */
    public function __construct(private readonly array $sites)
    {
    }

    public function steps(): array
    {
        return collect($this->sites)->map(fn ($site) => $this->makeStep($site))->toArray();
    }

    private function makeStep(array|string $site): Step
    {
        return new SiteStep(new Site($site));
    }
}
