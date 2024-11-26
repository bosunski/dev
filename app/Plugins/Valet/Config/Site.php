<?php

namespace App\Plugins\Valet\Config;

use Illuminate\Support\Str;

/**
 * @phpstan-import-type RawSiteConfig from ValetConfig
 */
class Site
{
    public readonly string $type;

    private readonly string $host;

    public readonly ?string $proxy;

    public readonly bool $secure;

    /**
     * @param RawSiteConfig|string $site
     */
    public function __construct(array|string $site)
    {
        if (is_array($site)) {
            $this->type = isset($site['proxy']) ? 'proxy' : 'link';
            $this->host = $site['host'];
            $this->proxy = $site['proxy'] ?? null;
            $this->secure = $site['secure'] ?? true;
        } else {
            $this->type = 'link';
            $this->host = $site;
            $this->proxy = null;
            $this->secure = true;
        }
    }

    public function host(string $tld = 'test'): string
    {
        return Str::of($this->host)->before(".$tld")->toString();
    }

    public function vhost(string $tld = 'test'): string
    {
        return Str::of($this->host($tld))->append(".$tld")->toString();
    }
}
