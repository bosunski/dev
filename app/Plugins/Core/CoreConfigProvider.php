<?php

namespace App\Plugins\Core;

use App\Dev;
use App\Plugin\Capability\ConfigProvider;
use App\Plugins\Core\Resolvers\CommandResolver;
use App\Plugins\Core\Resolvers\MySqlResolver;
use App\Plugins\Core\Resolvers\ScriptResolver;
use App\Plugins\Core\Steps\EnvSubstituteStep;
use App\Plugins\Core\Steps\ShadowEnv\EnsureShadowEnvStep;
use App\Plugins\Core\Steps\ShadowEnv\ShadowEnvStep;

class CoreConfigProvider implements ConfigProvider
{
    public function __construct(protected Dev $dev)
    {
    }

    public function steps(): array
    {
        return [
            new EnsureShadowEnvStep(),
            new ShadowEnvStep($this->dev),
            new EnvSubstituteStep($this->dev->config),
        ];
    }

    public function validate(): bool
    {
        return true;
    }

    public function stepResolvers(): array
    {
        return [
            'script'  => $scriptResolver = new ScriptResolver(),
            'custom'  => $scriptResolver,
            'command' => new CommandResolver($this->dev->config->commands()),
            'mysql'   => new MySqlResolver(),
        ];
    }
}
