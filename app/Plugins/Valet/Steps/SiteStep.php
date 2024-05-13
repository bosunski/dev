<?php

namespace App\Plugins\Valet\Steps;

use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Config\Site;
use Exception;

class SiteStep implements Step
{
    public function __construct(private readonly Site $site, protected string $valetBinary = 'valet')
    {
    }

    public function name(): string
    {
        return "Creating Valet site: {$this->site->host}";
    }

    /**
     * @throws Exception
     */
    public function run(Runner $runner): bool
    {
        $command = match ($this->site->type) {
            'link'  => "{$this->valetBinary} link {$this->site->host}" . ($this->site->secure ? ' --secure' : ''),
            'proxy' => "{$this->valetBinary} proxy {$this->site->host} {$this->site->proxy}" . ($this->site->secure ? ' --secure' : ''),
            default => throw new UserException("Unknown site type: {$this->site->type}"),
        };

        return $runner->exec($command);
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
