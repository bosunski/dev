<?php

namespace App\Config\Valet;

use Illuminate\Support\Str;

class Site
{
    public readonly string $type;

    public readonly string $host;

    public readonly ?string $proxy;

    public readonly bool $secure;


    public function __construct(array|string $site)
    {
        if (is_array($site)) {
            $this->type = isset($site['proxy']) ? 'proxy' : 'link';
            $this->host = $this->createHost($site['host']);
            $this->proxy = $site['proxy'] ?? null;
            $this->secure = $site['secure'] ?? true;
        } else {
            $this->type = 'link';
            $this->host = $this->createHost($site);
            $this->proxy = null;
            $this->secure = true;
        }
    }

    private function createHost(string $host): string
    {
        return Str::of($host)->before('.test')->toString();
    }
}
