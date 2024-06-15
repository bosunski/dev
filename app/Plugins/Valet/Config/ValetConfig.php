<?php

namespace App\Plugins\Valet\Config;

use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Steps\InstallValetStep;
use App\Plugins\Valet\Steps\LinkPhpStep;
use App\Plugins\Valet\Steps\PostUpStep;
use App\Plugins\Valet\Steps\PrepareValetStep;
use App\Plugins\Valet\Steps\SiteStep;
use Exception;

use function Illuminate\Filesystem\join_paths;

/**
 * @phpstan-type RawExtensionConfig array{
 *     before?: string,
 *     options?: array<string, string>
 * }
 *
 * @phpstan-type RawPhpConfig array{
 *    version: string,
 *    extensions?: array<string, RawExtensionConfig | true>
 * }
 *
 * @phpstan-type RawSiteConfig array{
 *      proxy?: string,
 *      secure?: bool,
 *      host: string
 * }
 *
 * @phpstan-type RawValetConfig array{
 *     sites?: array<RawSiteConfig | string>,
 *     php?: RawPhpConfig|string
 * }
 *
 * @phpstan-type RawValetEnvironment array{
 *      bin: string,
 *      dir: string,
 *      version: string,
 *      extensionPath: string,
 *      cwd: string,
 *      home: string,
 *      pecl: string,
 *      valet: array{bin: string, path: string, tld: string},
 *      composer: string
 * }
 */
class ValetConfig implements Config
{
    /**
     * @param RawValetConfig $config
     * @param RawValetEnvironment $environment
     *
     * @return void
     */
    public function __construct(protected readonly array $config, protected array $environment)
    {
    }

    /**
     * @return array<int, Step|Config>
     * @throws Exception
     */
    public function steps(): array
    {
        $steps = [
            new InstallValetStep($this->environment['composer'], $this->environment['valet']['bin']),
            new PrepareValetStep(),
        ];

        if (isset($this->config['php'])) {
            $config = $this->config['php'];
            $steps[] = is_array($config) ? new PhpConfig($config, $this->environment) : new LinkPhpStep($config, $this->environment);
        }

        foreach ($this->sites() as $site) {
            $steps[] = new SiteStep($site, $this);
        }

        $steps[] = new PostUpStep($this);

        return $steps;
    }

    /**
     * @return Site[]
     */
    public function sites(): array
    {
        return array_map(fn (array|string $site): Site => new Site($site, $this->environment['valet']['tld']), $this->config['sites'] ?? []);
    }

    public function path(string $path = ''): string
    {
        if (empty($path)) {
            return join_paths($this->environment['valet']['path']);
        }

        return join_paths($this->environment['valet']['path'], $path);
    }

    public function nginxPath(string $path = ''): string
    {
        if (empty($path)) {
            return $this->path('Nginx');
        }

        return $this->path('Nginx' . DIRECTORY_SEPARATOR . $path);
    }

    public function bin(): string
    {
        return $this->environment['valet']['bin'];
    }
}
