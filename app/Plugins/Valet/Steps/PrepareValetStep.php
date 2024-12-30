<?php

namespace App\Plugins\Valet\Steps;

use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Core\Steps\ShadowEnv\ShadowEnvStep;
use App\Plugins\Valet\Config\ValetConfig;

use function Illuminate\Filesystem\join_paths;

class PrepareValetStep implements Step
{
    public function __construct(protected ValetConfig $config)
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

        return $runner->execute(new ShadowEnvStep($this->config->dev)) && mkdir($path, recursive: true);
    }

    private function gatherValetFacts(Runner $runner): void
    {
        $valet = $this->valetBinPath($runner);
        $result = $runner->process("$valet --version")->run();
        if (! $result->successful()) {
            throw new UserException('Valet is not installed');
        }

        $version = trim($result->output());
        $tldCommand = $runner->config->isDarwin() ? 'tld' : 'domain';

        foreach ([
            'version' => $version,
            'bin'     => $this->valetBinPath($runner),
            'php'     => $runner->config->path('bin/php'),
        ] as $key => $value) {
            $this->config->env->put($key, $value);
        }

        $jsonConfig = $this->config();
        if (isset($jsonConfig[$tldCommand])) {
            $this->config->env->put('tld', $jsonConfig[$tldCommand]);
        }

        $this->config->dev->updateEnvironment();
    }

    private function config(): array
    {
        return $this->config->env->json();
    }

    private function valetBinPath(Runner $runner): string
    {
        /**
         * It's important to use global so the command won't fail if
         * the current directory doesn't have a composer.json file.
         */
        $result = $runner->process('composer global config home --no-interaction')->run();
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
