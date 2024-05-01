<?php

namespace App\Plugins\Valet\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Config\Site;
use Exception;

class SiteStep implements Step
{
    public function __construct(private readonly Site $site)
    {
    }

    public function name(): string
    {
        return "Creating Valet site: {$this->site->host}";
    }

    /**
     * @throws Exception
     */
    public function command(): string
    {
        return match ($this->site->type) {
            'link'  => "{$this->herBinary()} link {$this->site->host}" . ($this->site->secure ? ' --secure' : ''),
            'proxy' => "{$this->herBinary()} proxy {$this->site->host} {$this->site->proxy}" . ($this->site->secure ? ' --secure' : ''),
            default => throw new Exception("Unknown site type: {$this->site->type}"),
        };
    }

    private function herBinary(): string
    {
        return 'valet';
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
        return false;
    }

    public function id(): string
    {
        return "valet.site.{$this->site->host}";
    }
}
