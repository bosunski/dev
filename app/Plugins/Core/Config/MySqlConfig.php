<?php

namespace App\Plugins\Core\Config;

use App\Plugin\Contracts\Config;
use App\Plugins\Core\Steps\MySQL\CreateDatabaseStep;
use App\Plugins\Core\Steps\MySQL\StartContainerStep;

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
    public function __construct(protected array $config)
    {
    }

    public function steps(): array
    {
        return [
            new StartContainerStep(),
            new CreateDatabaseStep($this->config['databases']),
        ];
    }
}
