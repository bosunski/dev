<?php

namespace App\Plugins\Valet\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugin\Contracts\Step\Deferred;
use App\Plugins\Valet\Config\ValetConfig;

/**
 * @phpstan-import-type RawValetEnvironment from ValetConfig
*/
class PostUpStep implements Deferred, Step
{
    public function __construct(protected readonly ValetConfig $config)
    {
    }

    public function id(): string
    {
        return 'valet-post-up';
    }

    public function name(): string
    {
        return 'Post Valet Run';
    }

    public function run(Runner $runner): bool
    {
        foreach ($this->config->sites() as $site) {
            $md5Path = $runner->config()->globalPath("valet/sites/$site->virtualHost.md5");
            $nginxPath = $this->config->nginxPath($site->virtualHost);

            if (! $md5 = md5_file($nginxPath)) {
                continue;
            }

            file_put_contents($md5Path, $md5);
        }

        return true;
    }

    public function done(Runner $runner): bool
    {
        return false;
    }
}
