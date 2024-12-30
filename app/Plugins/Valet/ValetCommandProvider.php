<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Capability\CommandProvider;
use App\Plugins\Valet\Config\ValetConfig;

/**
 * @phpstan-import-type RawValetConfig from ValetConfig
 */
class ValetCommandProvider implements CommandProvider
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
        /** @var RawValetConfig|array{} $rawSpcConfig */
        $rawSpcConfig = $this->dev->config->up()->get(ValetPlugin::NAME) ?? [];
        if (empty($rawSpcConfig)) {
            return [];
        }

        return [
            'valet:restart' => [
                'desc' => 'Restart Valet services',
                'run'  => ['$VALET_BIN', 'restart'],
            ],
        ];
    }
}
