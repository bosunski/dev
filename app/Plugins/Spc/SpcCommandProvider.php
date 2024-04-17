<?php

namespace App\Plugins\Spc;

use App\Dev;
use App\Plugin\Capability\CommandProvider;
use App\Plugins\Spc\Config\SpcConfig;

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
        $rawSpcConfig = $this->dev->config->up()->get(SpcPlugin::NAME) ?? [];
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