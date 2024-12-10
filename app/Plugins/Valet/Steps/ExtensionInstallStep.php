<?php

namespace App\Plugins\Valet\Steps;

use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Valet\Config\ValetConfig;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Str;
use Throwable;

/**
 * @phpstan-import-type RawValetEnvironment from ValetConfig
 * @phpstan-import-type RawExtensionConfig from ValetConfig
*/
class ExtensionInstallStep implements Step
{
    /**
     * @param string $name
     * @param string $cwd
     * @param RawExtensionConfig|true $config
     * @return void
     */
    public function __construct(protected readonly string $name, protected string $cwd, protected readonly array|true $config)
    {
    }

    public function name(): string
    {
        return "Install and Link PHP extension: $this->name";
    }

    /**
     * @throws Throwable
     */
    public function run(Runner $runner): bool
    {
        $this->ensureIniDirectoryExists($runner);

        if ($this->installed($runner)) {
            return $this->enableExtension($runner);
        }

        if ($this->config['before'] ?? false) {
            throw_unless($runner->exec($this->config['before']), new UserException("Failed to run before command: {$this->config['before']}"));
        }

        $phpBin = realpath($runner->config->path('bin/php'));
        if (! $phpBin) {
            throw new UserException("Linked PHP binary $phpBin not found");
        }

        $phpBinDir = dirname($phpBin);
        $peclBin = "$phpBinDir/pecl";

        return $runner->exec("$peclBin install -D '{$this->getOptions()}' $this->name")
            && $this->enableExtension($runner);
    }

    protected function currentPhpExtensionPath(Runner $runner): string
    {
        $phpBin = $runner->config->path('bin/php');

        return trim($runner->withoutEnv()->process([$phpBin, '-nr', '"echo ini_get(\'extension_dir\');"'])->run()->throw()->output());
    }

    private function getOptions(): string
    {
        if (! is_array($this->config) || empty($this->config['options'] ?? [])) {
            return '';
        }

        return collect($this->config['options'])->map(function ($value, $key): string {
            preg_match_all('/`([^`]*)`/', $value, $matches);

            foreach ($matches[1] ?? [] as $match) {
                $value = Str::replaceFirst("`$match`", trim(`$match` ?? ''), $value);
            }

            return sprintf('%s="%s"', $key, $value);
        })->join(' ');
    }

    private function enableExtension(Runner $runner): bool
    {
        if ($this->enabled($runner)) {
            return true;
        }

        $name = Str::before($this->name, '-');

        return (bool) @file_put_contents($runner->config()->devPath("php.d/$name.ini"), "extension={$this->extensionPath($runner)}");
    }

    private function ensureIniDirectoryExists(Runner $runner): void
    {
        @mkdir($runner->config()->devPath('php.d'), 0755, true);
    }

    public function done(Runner $runner): bool
    {
        return $this->enabled($runner);
    }

    public function installed(Runner $runner): bool
    {
        return is_file($this->extensionPath($runner));
    }

    private function extensionPath(Runner $runner): string
    {
        return $this->currentPhpExtensionPath($runner) . DIRECTORY_SEPARATOR . Str::before($this->name, '-') . '.so';
    }

    private function enabled(Runner $runner): bool
    {
        try {
            $name = Str::before($this->name, '-');
            $version = Str::after($this->name, '-');

            $result = $runner->process([$runner->config->path('bin/php'), '--ri', $name])->run()->throw();

            return Str::contains($result->output(), "$version");
        } catch (ProcessFailedException) {
            return false;
        }
    }

    public function id(): string
    {
        return "$this->cwd.php.extension.$this->name";
    }
}
