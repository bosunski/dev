<?php

namespace App\Config\Herd;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Step\Herd\SiteStep;

/**
 * @phpstan-import-type RawSiteConfig from HerdConfig
 */
class Sites implements Config
{
    /**
     * @param array<RawSiteConfig|string> $sites
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
