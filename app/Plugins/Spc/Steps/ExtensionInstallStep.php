<?php

namespace App\Plugins\Spc\Steps;

use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

class ExtensionInstallStep implements Step
{
    public function __construct(protected readonly string $name, protected readonly array $environment, protected readonly array $config = [])
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

        if ($this->installed()) {
            return $this->enableExtension($runner);
        }

        if ($this->config['before'] ?? false) {
            throw_unless($runner->exec($this->config['before']), new UserException("Failed to run before command: {$this->config['before']}"));
        }

        return $runner->exec("{$this->environment['pecl']} install -D '{$this->getOptions()}' $this->name")
            && $this->enableExtension($runner);
    }

    private function getOptions(): string
    {
        if (empty($this->config) || empty($this->config['options'] ?? [])) {
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
        if ($this->enabled()) {
            return true;
        }

        $name = Str::before($this->name, '-');

        return @file_put_contents($runner->config()->cwd(".garm/php.d/$name.ini"), "extension={$this->extensionPath()}");
    }

    private function ensureIniDirectoryExists(Runner $runner): void
    {
        File::makeDirectory($runner->config()->cwd('.garm/php.d'), 0755, true, true);
    }

    public function done(Runner $runner): bool
    {
        return $this->enabled();
    }

    public function installed(): bool
    {
        return is_file($this->extensionPath());
    }

    private function extensionPath(): string
    {
        return $this->environment['extensionPath'] . DIRECTORY_SEPARATOR . Str::before($this->name, '-') . '.so';
    }

    public function enabled(): bool
    {
        try {
            $process = Process::fromShellCommandline("{$this->environment['bin']} -m")->mustRun();

            return Str::of($process->getOutput())->after('[PHP Modules]')->contains(Str::before($this->name, '-'));
        } catch (ProcessFailedException) {
            return false;
        }
    }

    public function id(): string
    {
        // TODO: Include path to make it unique across projects
        return "{$this->environment['cwd']}.php.extension.$this->name";
    }
}
