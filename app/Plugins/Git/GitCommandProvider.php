<?php

namespace App\Plugins\Git;

use App\Plugin\Capability\CommandProvider;
use App\Plugins\Git\Commands\CloneCommand;

class GitCommandProvider implements CommandProvider
{
    /**
     * @inheritDoc
     */
    public function getCommands(): array
    {
        return [
            new CloneCommand(),
        ];
    }

    public function getConfigCommands(): array
    {
        return [
            'check' => [
                'desc' => 'Check if git is installed',
                'run'  => 'git --version',
            ],
        ];
    }
}
