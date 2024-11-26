<?php

namespace App\Plugins\Valet\Config;

use App\Config\Config as ConfigConfig;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Steps\ExtensionInstallStep;
use App\Plugins\Valet\Steps\InstallValetStep;
use App\Plugins\Valet\Steps\LinkPhpStep;
use App\Plugins\Valet\Steps\PostUpStep;
use App\Plugins\Valet\Steps\PrepareValetStep;
use App\Plugins\Valet\Steps\SiteStep;
use Exception;
use Illuminate\Support\Collection;

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
 *      cwd: string,
 *      home: string,
 *      pecl: string,
 *      valet: array{bin: string, path: string, tld: string},
 *      composer: string
 * }
 */
class ValetConfig implements Config
{
    public const Tld = 'test';

    public const DefaultPhpversion = '8.3';

    /**
     * @var Collection{"valet.tld": string}
     */
    public readonly Collection $env;

    /**
     * @param RawValetConfig $config
     * @param callable(): RawValetEnvironment $environment
     *
     * @return void
     */
    public function __construct(protected readonly array $config, public $environment, public readonly ConfigConfig $devConfig)
    {
        $this->env = new Collection();
    }

    /**
     * @return array<int, Step|Config>
     * @throws Exception
     */
    public function steps(): array
    {
        /**
         * PHP is required to run Valet, so we always link PHP first.
         */
        $phpConfig = $this->config['php'] ?? self::DefaultPhpversion;
        $steps = $this->phpSteps($phpConfig);

        array_push($steps, new InstallValetStep(), new PrepareValetStep($this));

        if (isset($this->config['php'])) {
            $steps = array_merge($steps, $this->phpSteps($this->config['php']));
        }

        foreach ($this->sites() as $site) {
            $steps[] = new SiteStep($site, $this);
        }

        $steps[] = new PostUpStep($this);

        return $steps;
    }

    /**
     * @param RawValetConfig['php'] $config
     * @return array<int, Step|Config>
     * @throws Exception
     */
    public function phpSteps(string|array $config): array
    {
        if (is_string($config)) {
            return [new LinkPhpStep($config)];
        }

        $steps = [];
        foreach ($config as $name => $value) {
            if ($name === 'version') {
                $steps[] = new LinkPhpStep($value);
                continue;
            }

            if ($name === 'extensions') {
                foreach ($value as $name => $config) {
                    $steps[] = new ExtensionInstallStep($name, $this->devConfig->cwd(), $config);
                }
                continue;
            }
        }

        return $steps;
    }

    /**
     * @return Site[]
     */
    public function sites(): array
    {
        return array_map(fn (array|string $site): Site => new Site($site), $this->config['sites'] ?? []);
    }

    public function nginxPath(string $path = ''): string
    {
        if (empty($path)) {
            return $this->path('Nginx');
        }

        return $this->path('Nginx' . DIRECTORY_SEPARATOR . $path);
    }

    public function path(string $path = ''): string
    {
        if (empty($path)) {
            return join_paths(($this->environment)()['valet']['path']);
        }

        return join_paths(($this->environment)()['valet']['path'], $path);
    }

    public function cwd(): string
    {
        return $this->devConfig->cwd();
    }
}
