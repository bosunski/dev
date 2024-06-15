<?php

namespace App\Plugins\Valet\Steps;

use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Config\Site;
use App\Plugins\Valet\Config\ValetConfig;
use Exception;

use function Illuminate\Filesystem\join_paths;

/**
 * @phpstan-import-type RawValetEnvironment from ValetConfig
 */
class SiteStep implements Step
{
    private string $valetBinary = 'valet';

    /**
     * @param Site $site
     * @param RawValetEnvironment['valet'] $valetEnv
     * @return void
     */
    public function __construct(private readonly Site $site, protected array $valetEnv)
    {
        $this->valetBinary = $valetEnv['bin'];
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
        $nginxPath = join_paths($this->valetEnv['nginxPath'], $this->site->virtualHost());
        if (! is_file($nginxPath)) {
            return false;
        }

        $md5Path = $runner->config()->globalPath("valet/sites/{$this->site->virtualHost()}.md5");
        if (! is_file($md5Path)) {
            return false;
        }

        $md5 = md5_file($nginxPath);
        if (! $md5) {
            return false;
        }

        return $md5 === file_get_contents($md5Path);
    }

    public function id(): string
    {
        return "valet.site.{$this->site->host}";
    }
}
