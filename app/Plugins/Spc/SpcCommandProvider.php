<?php

namespace App\Plugins\Spc;

use App\Dev;
use App\Plugin\Capability\CommandProvider;
use App\Plugins\Spc\Config\SpcConfig;

/**
 * @phpstan-import-type RawSpcConfig from SpcConfig
 */
class SpcCommandProvider implements CommandProvider
{
    public function __construct(protected Dev $dev)
    {
    }

    /**
     * @inheritDoc
     */
    public function getCommands(): array
    {
        return [];
    }

    public function getConfigCommands(): array
    {
        /** @var RawSpcConfig|array{} $rawSpcConfig */
        $rawSpcConfig = $this->dev->config->up()->get(SpcConfig::Name) ?? [];
        if (empty($rawSpcConfig)) {
            return [];
        }

        $config = new SpcConfig($rawSpcConfig, $this->dev->config);
        $combine = $config->combine();

        if (empty($combine)) {
            return [];
        }

        return [
            'spc:combine' => [
                'desc' => 'Combine micro.sfx and php code together',
                'run'  => "{$config->bin()} micro:combine -M {$config->sfx()} -O {$combine['output']} {$combine['input']}",
            ],
        ];
    }
}
