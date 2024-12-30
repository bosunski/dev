<?php

namespace App\Plugins\Core\Config;

use App\Dev;
use App\Plugin\Contracts\Config;
use App\Plugins\Core\Steps\MySQL\CreateDatabaseStep;
use App\Plugins\Core\Steps\MySQL\EnsureDockerStep;
use App\Plugins\Core\Steps\MySQL\StartContainerStep;
use App\Plugins\Core\Steps\MySQL\UpdateEnvironmentStep;

/**
 * @phpstan-type RawMySqlConfig array{
 *   databases: string|array<string>,
 *   version?: string
 * }
 */
class MySqlConfig implements Config
{
    /**
     * @param RawMySqlConfig $config
     * @return void
     */
    public function __construct(protected array $config, public readonly Dev $dev)
    {
    }

    public function steps(): array
    {
        return [
            new EnsureDockerStep(),
            new StartContainerStep(),
            new UpdateEnvironmentStep($this),
            new CreateDatabaseStep($this->config['databases']),
        ];
    }
}
