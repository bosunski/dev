<?php

namespace App\Plugins\Valet\Steps;

use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Config\Site;
use App\Plugins\Valet\Config\ValetConfig;
use Exception;

/**
 * @phpstan-import-type RawValetEnvironment from ValetConfig
 */
class SiteStep implements Step
{
    /**
     * @param Site $site
     * @param ValetConfig $config
     * @return void
     */
    public function __construct(private readonly Site $site, protected ValetConfig $config)
    {
    }

    public function name(): string
    {
        return "Creating Valet site: {$this->site->host()}";
    }

    /**
     * @throws Exception
     */
    public function run(Runner $runner): bool
    {
        $valetBinary = $this->config->env->get('bin');
        $host = $this->site->host($this->tld());

        $command = match ($this->site->type) {
            'link'  => [$valetBinary, 'link', $host],
            'proxy' => [$valetBinary, 'proxy', $host, "{$this->site->proxy}"],
            default => throw new UserException("Unknown site type: {$this->site->type}"),
        };

        if ($this->site->secure) {
            $command[] = '--secure';
        }

        if ($this->site->type === 'link') {
            $command = [...$command, '&&', $valetBinary, 'isolate', '--site', $host, $this->config->php()];
        }

        return $runner->exec($command);
    }

    private function tld(): string
    {
        return $this->config->env->get('tld');
    }

    /**
     * @throws Exception
     */
    public function done(Runner $runner): bool
    {
        $tld = $this->tld();
        $nginxPath = $this->config->nginxPath($this->site->vhost($tld));
        if (! is_file($nginxPath)) {
            return false;
        }

        $md5Path = $runner->config()->globalPath("valet/sites/{$this->site->vhost($tld)}.md5");
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
        return "valet.site.{$this->site->host()}";
    }
}
