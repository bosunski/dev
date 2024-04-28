<?php

namespace App\Plugins\Valet\Config;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Steps\SiteStep;

/**
 * @phpstan-import-type RawSiteConfig from ValetConfig
 */
class Sites implements Config
{
    /**
     * @param array<int, RawSiteConfig|string> $sites
     */
    public function __construct(private readonly array $sites)
    {
    }

    public function steps(): array
    {
        return array_map(fn (string|array $site) => $this->makeStep($site), $this->sites);
    }

    /**
     * @param RawSiteConfig|string $site
     * @return Step
     */
    private function makeStep(array|string $site): Step
    {
        return new SiteStep(new Site($site));
    }
}
