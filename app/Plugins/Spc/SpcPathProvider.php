<?php

namespace App\Plugins\Spc;

use App\Dev;
use App\Plugin\Capability\PathProvider;
use App\Plugins\Spc\Config\SpcConfig;

class SpcPathProvider implements PathProvider
{
    public function __construct(protected Dev $dev)
    {
    }

    public function paths(): array
    {
        $config = $this->dev->config->up()->get(SpcConfig::Name) ?? [];
        if (empty($config)) {
            return [];
        }

        $phpVersion = $config['version'] ?? '8.3';
        $path = sprintf(
            '%s/%s/buildroot/bin',
            $this->dev->config->globalPath('spc'),
            (string) $phpVersion,
        );

        return [$path];
    }
}
