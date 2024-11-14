<?php

namespace App\Plugins\Spc\Command;

use App\Dev;
use App\Plugins\Spc\Steps\SpcInstallStep;
use LaravelZero\Framework\Commands\Command;

class SpcInstallCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'spc:install';

    /**
     * @var string
     */
    protected $description = 'Install latest SPC binary';

    public function handle(Dev $dev): int
    {
        return $dev->runner->execute(new SpcInstallStep(true))
            ? self::SUCCESS
            : self::FAILURE;
    }
}
