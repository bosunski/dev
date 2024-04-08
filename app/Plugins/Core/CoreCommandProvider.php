<?php

namespace App\Plugins\Core;

use App\Plugin\Capability\CommandProvider;
use App\Plugins\Core\Commands\CdCommand;
use App\Plugins\Core\Commands\CloneCommand;
use App\Plugins\Core\Commands\UpCommand;

class CoreCommandProvider implements CommandProvider
{
    /**
     * @inheritDoc
     */
    public function getCommands(): array
    {
        return [
            new CloneCommand(),
            new CdCommand(),
            app(UpCommand::class),
        ];
    }

    public function getConfigCommands(): array
    {
        return [
            'check' => [
                'desc' => 'Check if git is installedas',
                'run'  => 'git --version',
            ],
        ];
    }
}
