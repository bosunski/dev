<?php

namespace App\Step;

use App\Execution\Runner;
use Illuminate\Support\Str;

class LockPhpStep implements StepInterface
{
    public readonly string $id;
    public function __construct()
    {
        $this->id = Str::random(10);
    }

    public function id(): string
    {
        return $this->id;
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
        $herdPhpPath = "{$_SERVER['HOME']}/Library/Application\ Support/Herd/bin/php{$config->getPhp()}";
        $herdPhpPath = "/opt/homebrew/Cellar/php/{$config->getPhp()}/bin/php";
        $binDir = "{$config->path()}/bin";
        $binPath = "{$config->path()}/bin/php";

        return $runner->exec("mkdir -p $binDir && ln -sf $herdPhpPath $binPath");
    }

    public function done(Runner $runner): bool
    {
        return $this->run($runner);
    }
}
