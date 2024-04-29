<?php

namespace App\Plugins\Composer\Config;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugins\Composer\Steps\AuthStep;

/**
 * @phpstan-import-type RawAuth from ComposerConfig
 */
class AuthConfig implements Config
{
    /**
     * @param RawAuth[] $auth
     * @return void
     */
    public function __construct(private readonly array $auth)
    {
    }

    /**
     * @return array<int, Step|Config>
     */
    public function steps(): array
    {
        return array_map(fn (array $auth) => $this->makeStep($auth), $this->auth);
    }

    /**
     * @param RawAuth $auth
     */
    private function makeStep(array $auth): Step
    {
        return new AuthStep(new Auth($auth));
    }
}
