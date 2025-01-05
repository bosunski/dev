<?php

namespace App\Plugins\Valet\Config;

use App\Dev;
use App\Exceptions\UserException;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Steps\ExtensionInstallStep;
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

    public const DefaultPhpversion = '8.4';

    public string $currentPhpVersion = self::DefaultPhpversion;

    public readonly LocalValetConfig $env;

    /**
     * @param RawValetConfig $config
     *
     * @return void
     */
    public function __construct(protected readonly array $config, public readonly Dev $dev, LocalValetConfig $localValetConfig)
    {
        $this->env = $localValetConfig;
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
        $steps = $this->phpSteps($phpConfig, isset($this->config['php']));

        array_push($steps, new InstallValetStep(), new PrepareValetStep($this));

        foreach ($this->sites() as $site) {
            $steps[] = new SiteStep($site, $this);
        }

        $steps[] = new PostUpStep($this);

        return $steps;
    }

    /**
     * @param RawPhpConfig|string $config
     * @return array<int, Step|Config>
     * @throws Exception
     */
    private function phpSteps(string|array $config, bool $link = true): array
    {
        if ($link && is_string($config)) {
            $this->currentPhpVersion = $config;

            return [new LinkPhpStep($config, $this->dev->config->brewPath('Cellar'), $this)];
        }

        if (! is_array($config)) {
            return [];
        }

        $steps = [];
        foreach ($config as $name => $value) {
            if ($link && $name === 'version') {
                $this->currentPhpVersion = $value;

                $steps[] = new LinkPhpStep($value, $this->dev->config->brewPath('Cellar'), $this);
                continue;
            }

            if ($name === 'extensions') {
                foreach ($value as $name => $config) {
                    $steps[] = new ExtensionInstallStep($name, $this->dev->config->cwd(), $config);
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
        assert($valetDir = $this->env->get('dir'));

        return join_paths($valetDir, $path);
    }

    public function cwd(): string
    {
        return $this->dev->config->cwd();
    }

    public function php(): string
    {
        $source = LinkPhpStep::PHP_VERSION_MAP[$this->currentPhpVersion] ?? null;
        if (! $source) {
            throw new UserException("Unknown PHP version '$this->currentPhpVersion' in configuration.", 'Supported versions: ' . implode(', ', array_keys(LinkPhpStep::PHP_VERSION_MAP)));
        }

        if ($source === 'php') {
            $source .= '@' . self::DefaultPhpversion;
        }

        return $source;
    }
}
