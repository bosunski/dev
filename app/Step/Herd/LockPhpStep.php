<?php

namespace App\Step\Herd;

use App\Execution\Runner;
use App\Step\StepInterface;
use Illuminate\Support\Str;

class LockPhpStep implements StepInterface
{
    public readonly string|float $id;

    public function __construct(protected readonly string|float $version)
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
        if (is_float($this->version)) {
            $version = (string) intval($this->version * 10);
        } else {
            $version = intval((float) $this->version * 10);
        }

        // TODO: Convert 8.3.4 to 83
        $herdPhpPath = "{$_SERVER['HOME']}/Library/Application Support/Herd/bin/php$version";

        if (! is_file($herdPhpPath)) {
            $runner->io()->error("PHP $herdPhpPath is not installed");
            return false;
        }

        $binDir = "{$config->path()}/bin";
        $binPath = "{$config->path()}/bin/php";

        $herdPhpPath = escapeshellarg($herdPhpPath);
        return $runner->exec("mkdir -p $binDir && ln -sf $herdPhpPath $binPath");
    }

    public function done(Runner $runner): bool
    {
        return $this->run($runner);
    }
}
