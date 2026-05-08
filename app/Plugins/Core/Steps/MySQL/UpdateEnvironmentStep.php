<?php

namespace App\Plugins\Core\Steps\MySQL;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Core\Config\MySqlConfig;

class UpdateEnvironmentStep implements Step
{
    public function __construct(protected MySqlConfig $config)
    {
    }

    public function id(): string
    {
        return "mysql-update-environment-{$this->config->dev->config->path()}";
    }

    public function name(): ?string
    {
        return 'Update MySQL environment';
    }

    public function run(Runner $runner): bool
    {
        $runner->config->putenv('DEV_MYSQL_HOST', '127.0.0.1');

        return $this->config->dev->updateEnvironment();
    }

    public function done(Runner $runner): bool
    {
        return false;
    }
}
