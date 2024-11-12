<?php

namespace App\Plugins\Core\Steps\MySQL;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Illuminate\Process\Exceptions\ProcessFailedException;

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
        // First we need to check if the container already exists and not running
        $command = 'docker kill dev-mysql; docker rm dev-mysql -f';
        echo $runner->process($command)->run()->output();

        $dataDir = $runner->config()->globalPath('mysql/data');
        $command = "docker run --rm -v $dataDir:/var/lib/mysql -l dev.orbstack.domains=mysql.dev.local --name dev-mysql -e MYSQL_ALLOW_EMPTY_PASSWORD='yes' -d mysql:8.3.0 --max-allowed-packet=512M";

        try {
            return $runner->process($command)->run()->throw()->successful();
        } catch (ProcessFailedException $e) {
            echo $e->getMessage();

            return false;
        }
    }

    public function done(Runner $runner): bool
    {
        return $runner->process('docker ps | grep dev-mysql')->run()->successful();
    }
}
