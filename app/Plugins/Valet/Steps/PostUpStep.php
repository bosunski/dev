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

    public function name(): string
    {
        return 'Post Valet Run';
    }

    public function run(Runner $runner): bool
    {
        $tld = $this->config->env->get('tld');
        foreach ($this->config->sites() as $site) {
            $host = $site->vhost($tld);
            $md5Path = $runner->config()->globalPath("valet/sites/$host.md5");
            $nginxPath = $this->config->nginxPath($host);

            if (! $md5 = md5_file($nginxPath)) {
                continue;
            }

            $runner->io()->writeln("Storing state of $nginxPath");

            file_put_contents($md5Path, $md5);
        }

        return true;
    }

    public function done(Runner $runner): bool
    {
        return false;
    }

    public function id(): string
    {
        return "valet.post-up.{$this->config->cwd()}";
    }
}
