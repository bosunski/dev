<?php

namespace App\Plugins\Composer;

use App\Dev;
use App\Plugin\Capability\PathProvider;

class ComposerPathProvider implements PathProvider
{
    public function __construct(private Dev $dev)
    {
    }

    public function paths(): array
    {
        return [
            $this->dev->config->home('.composer/vendor/bin'),
        ];
    }
}
