<?php

namespace App\Plugins\Core\Steps\MySQL;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;

class StartContainerStep implements Step
{
    public function id(): string
    {
        return 'mysql-start-container';
    }

    public function name(): ?string
    {
        return 'Start MySQL container';
    }

    public function run(Runner $runner): bool
    {
        $dataDir = $runner->config()->globalPath('mysql/data');
        $command = "docker run --rm -v $dataDir:/var/lib/mysql -l dev.orbstack.domains=mysql.dev.local --name dev-mysql -e MYSQL_ALLOW_EMPTY_PASSWORD='yes' -d mysql:8.3.0";

        return $runner->process($command)->run()->successful();
    }

    public function done(Runner $runner): bool
    {
        return $runner->process('docker ps | grep dev-mysql')->run()->successful();
    }
}
