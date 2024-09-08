<?php

namespace App\Plugins\Spc;

use App\Dev;
use App\Plugin\Capability\CommandProvider;
use App\Plugins\Spc\Command\SpcInstallCommand;
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
        return [
            new SpcInstallCommand(),
        ];
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

        $commands = [
            'spc:rebuild' => [
                'desc' => 'Rebuild PHP binaries',
                'run'  => $config->buildCommand(true),
                'path' => $config->phpPath(),
            ],
            'spc:clean' => [
                'desc' => 'Remove built binaries and downloads',
                'run'  => ['rm', '-rf', $config->phpPath()],
            ],
        ];

        if (! empty($combine)) {
            $commands['spc:combine'] = [
                'desc' => 'Combine micro.sfx and php code together',
                'run'  => "{$config->bin()} micro:combine -M {$config->sfx()} -O {$combine['output']} {$combine['input']}",
            ];
        }

        return $commands;
    }
}
