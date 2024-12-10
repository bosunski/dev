<?php

namespace App\Plugins\Valet\Steps;

use App\Dev;
use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Core\Steps\ShadowEnv\ShadowEnvStep;
use App\Plugins\Valet\Config\ValetConfig;

use function Illuminate\Filesystem\join_paths;

class PrepareValetStep implements Step
{
    public function __construct(protected ValetConfig $config, protected Dev $dev)
    {
    }

    public function name(): string
    {
        return 'Prepare Laravel Valet';
    }

    public function run(Runner $runner): bool
    {
        $this->gatherValetFacts($runner);

        $path = $runner->config()->globalPath('valet/sites');
        if (is_dir($path)) {
            return true;
        }

        return $runner->execute(new ShadowEnvStep($this->dev)) && mkdir($path, recursive: true);
    }

    private function gatherValetFacts(Runner $runner): void
    {
        $valet = $this->valetBinPath($runner);
        $result = $runner->process("$valet --version")->run();
        if (! $result->successful()) {
            throw new UserException('Valet is not installed');
        }

        $version = trim($result->output());
        $result = $runner->process("$valet paths")->run();
        if (! $result->successful()) {
            throw new UserException('Failed to get Valet paths');
        }

        $paths = explode(PHP_EOL, $result->output());
        $tldResult = $runner->process("$valet tld")->run();
        if (! $tldResult->successful()) {
            throw new UserException('Failed to get Valet TLD');
        }

        foreach ([
            'version' => $version,
            'bin'     => $this->valetBinPath($runner),
            'path'    => $paths[0],
            'tld'     => trim($tldResult->output()),
            'php'     => $runner->config->path('bin/php'),
            'dir'     => $runner->config->home('.config/valet'),
        ] as $key => $value) {
            $this->config->env->put($key, $value);
        }
    }

    private function valetBinPath(Runner $runner): string
    {
        $result = $runner->process('composer global config home')->run();
        if (! $result->successful()) {
            throw new UserException('Attempted to install Valet but it seems Composer is not installed or not in the PATH.');
        }

        $composerHomePath = trim($result->output());

        return join_paths($composerHomePath, 'vendor/bin/valet');
    }

    public function done(Runner $runner): bool
    {
        return false;
    }

    public function id(): string
    {
        return uniqid('valet.prepare.');
    }
}
