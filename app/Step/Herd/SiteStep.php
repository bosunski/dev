<?php

namespace App\Step\Herd;

use App\Config\Herd\Site;
use App\Execution\Runner;
use App\Step\StepInterface;
use Exception;

class SiteStep implements StepInterface
{
    public function __construct(private readonly Site $site)
    {
    }

    public function name(): string
    {
        return "Creating Herd site: {$this->site->host}";
    }

    /**
     * @throws Exception
     */
    public function command(): ?string
    {
        return match ($this->site->type) {
            'link'  => "{$this->herBinary()} link {$this->site->host}" . ($this->site->secure ? ' --secure' : ''),
            'proxy' => "{$this->herBinary()} proxy {$this->site->host} {$this->site->proxy}" . ($this->site->secure ? ' --secure' : ''),
            default => throw new Exception("Unknown site type: {$this->site->type}"),
        };
    }

    private function herBinary(): string
    {
        return escapeshellarg("{$_SERVER['HOME']}/Library/Application Support/Herd/bin/herd");
    }

    /**
     * @throws Exception
     */
    public function checkCommand(): ?string
    {
        return $this->command();
    }

    /**
     * @throws Exception
     */
    public function run(Runner $runner): bool
    {
        return $runner->exec($this->command());
    }

    /**
     * @throws Exception
     */
    public function done(Runner $runner): bool
    {
        return $runner->exec($this->checkCommand());
    }

    public function id(): string
    {
        return "herd.site.{$this->site->host}";
    }
}
