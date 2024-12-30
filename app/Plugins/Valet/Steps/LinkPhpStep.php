<?php

namespace App\Plugins\Valet\Steps;

use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Brew\Steps\BrewStep;
use App\Plugins\Valet\Config\ValetConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type RawValetEnvironment from ValetConfig
*/
class LinkPhpStep implements Step
{
    public const PHP_VERSION_MAP = [
        '8.4' => 'php',
        '8.3' => 'php@8.3',
        '8.2' => 'php@8.2',
        '8.1' => 'php@8.1',
        '8.0' => 'php@8.0',
        '7.4' => 'php@7.4',
    ];

    private bool $installed = false;

    private bool $linked = false;

    /**
     * @param string $version
     * @return void
     */
    public function __construct(protected readonly string $version, private readonly string $brewPath, private readonly ValetConfig $valetConfig)
    {
    }

    public function id(): string
    {
        return Str::random(10);
    }

    public function name(): string
    {
        return "Install and Link PHP v$this->version";
    }

    public function run(Runner $runner): bool
    {
        if (! $this->installed && ! $runner->execute(new BrewStep([$this->source($this->version)]))) {
            return false;
        }

        $sourcePhpPath = $this->phpPath($this->version);
        if (! is_file($sourcePhpPath)) {
            $runner->io()->error("PHP v$this->version is not installed");

            return false;
        }

        $binDirReady = is_dir($runner->config->path('bin')) || @mkdir($runner->config->path('bin'), recursive: true);

        return $binDirReady
            && $runner->exec("ln -sf $sourcePhpPath {$runner->config->path('bin/php')}")
            && $this->updateEnvironment($sourcePhpPath);
    }

    private function updateEnvironment(string $phpSourcePath): bool
    {
        $this->valetConfig->env->put('php', $phpSourcePath);

        return $this->valetConfig->dev->updateEnvironment();
    }

    private function source(string $version): string
    {
        $source = self::PHP_VERSION_MAP[$version] ?? null;
        if (! $source) {
            throw new UserException("Unknown PHP version '$version' in configuration.", 'Supported versions: ' . implode(', ', array_keys(self::PHP_VERSION_MAP)));
        }

        return $source;
    }

    /**
     * Get all PHP installations for a given version.
     *
     * @param string $source
     * @param string $version
     * @return Collection<int, string>
     */
    private function getPhpInstallations(string $source, string $version): Collection
    {
        $paths = glob("$this->brewPath/$source/$version.*/bin/php");
        assert($paths !== false, new UserException("Failed to find PHP installations for $version"));

        return collect($paths);
    }

    protected function phpPath(string $version): string
    {
        $source = $this->source($version);

        $paths = $this->getPhpInstallations($source, $version)->filter();
        if ($paths->isEmpty()) {
            throw new UserException("Valet: PHP $version is not installed in $this->brewPath/$source");
        }

        /**
         * There can be multiple PHP installations for a single version managed by Homebrew.
         * So, we need to find the latest version among them.
         */
        $versions = collect();
        foreach ($paths as $path) {
            preg_match("/$version\.\d+/", $path, $matches);
            $versions[$matches[0]] = $path;
        }

        $latest = $versions->keys()->first();
        if (! is_string($latest)) {
            // The PHP version is not found and probaly because the version is not installed
            // in this case, there should be a step to install the PHP version before we get here
            throw new UserException("Valet: PHP $version is not installed in $this->brewPath/$source.");
        }

        $versions->each(function (string $_, string $version) use (&$latest): void {
            if (version_compare($version, $latest, '>')) {
                $latest = $version;
            }
        });

        return $versions->get($latest);
    }

    public function done(Runner $runner): bool
    {
        $phpBin = '';

        try {
            $phpBin = $this->phpPath($this->version);
            $this->installed = true;

            $linkPath = $runner->config->path('bin/php');
            $this->linked = is_file($linkPath) && realpath($linkPath) === $phpBin;
        } catch (UserException) {
            $this->installed = false;
        }

        return $this->installed && $this->linked && $this->valetConfig->env->get('php') === $phpBin;
    }
}
