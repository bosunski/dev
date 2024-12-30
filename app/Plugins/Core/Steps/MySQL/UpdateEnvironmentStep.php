<?php

namespace App\Plugins\Core\Steps\MySQL;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Core\Config\MySqlConfig;
use Illuminate\Process\Exceptions\ProcessFailedException;

class UpdateEnvironmentStep implements Step
{
    public function __construct(protected MySqlConfig $config)
    {
    }

    public function id(): string
    {
        return 'mysql-update-environment';
    }

    public function name(): ?string
    {
        return 'Update MySQL environment';
    }

    public function run(Runner $runner): bool
    {
        try {
            $ipAddress = retry([100, 700, 2000], fn () => $runner->process('docker inspect -f "{{.NetworkSettings.IPAddress}}" dev-mysql')->run()->throw()->output());
            $runner->config->putenv('DEV_MYSQL_HOST', trim($ipAddress));

            return $this->config->dev->updateEnvironment();
        } catch (ProcessFailedException $e) {
            echo $e->getMessage();

            return false;
        }
    }

    public function done(Runner $runner): bool
    {
        return false;
    }
}
