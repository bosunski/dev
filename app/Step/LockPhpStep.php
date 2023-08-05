<?php

namespace App\Step;

use App\Execution\Runner;

class LockPhpStep implements StepInterface
{
    public function __construct()
    {
    }

    public function name(): string
    {
        return 'Lock PHP version';
    }

    public function command(): ?string
    {
        return null;
    }

    public function checkCommand(): ?string
    {
        return null;
    }

    public function run(Runner $runner): bool
    {
        $config = $runner->config();
        $php = $config->getPhp() * 10;
        $home = $_SERVER['HOME'];
        $herdPhpPath = "$home/Library/Application\ Support/Herd/bin/php$php";
        $binDir = "{$config->path()}/bin";
        $binPath = "{$config->path()}/bin/php";

        return $runner->exec("mkdir -p $binDir && ln -sf $herdPhpPath $binPath");
    }

    public function done(Runner $runner): bool
    {
        return $this->run($runner);
    }
}
