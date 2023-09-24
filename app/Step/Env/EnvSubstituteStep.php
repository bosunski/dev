<?php

namespace App\Step\Env;

use App\Config\Config;
use App\Execution\Runner;
use App\Step\StepInterface;
use Dotenv\Dotenv;

class EnvSubstituteStep implements StepInterface
{
    public function __construct(protected readonly Config $config)
    {
    }

    public function name(): string
    {
        return 'Substituting variables in .env file with discovered .env.example';
    }

    public function run(Runner $runner): bool
    {
        if (! $this->hasSampleEnvFile($runner->config()) || ! $this->hasEnvFile($runner->config())) {
            return true;
        }

        $sampleEnvContent = file_get_contents($runner->config()->cwd('.env.example'));
        $envContent = file_get_contents($runner->config()->cwd('.env'));

        // ToDo: Handle errors when parsing .env files
        $sampleEnvs = Dotenv::parse($sampleEnvContent);
        $currentEnvs = Dotenv::parse($sampleEnvContent);

        if (count($sampleEnvs) > 0) {
            $runner->io()->info('Substituting variables in .env file with discovered .env.example');
            $envContent .= "\n";
        }

        $envWasAdded = false;
        foreach ($sampleEnvs as $key => $value) {
            $hasValue = ! in_array($currentEnvs[$key], ["", "null", "NULL"]);
            if (str_contains($envContent, "$key=") &&  $hasValue) {
                continue;
            }

            $insert = "$key=\"$value\"";
            if (! $hasValue && ! in_array($value, ["", "null", "NULL"])) {
                $envContent = preg_replace("/$key=(.*)/m", $insert, $envContent);
                continue;
            }

            $envContent .= $insert . "\n";
            $envWasAdded = true;
        }

        if ($envWasAdded) {
            file_put_contents($runner->config()->cwd('.env'), $envContent);
        }

        return true;
    }

    public function done(Runner $runner): bool
    {
        return false;
    }

    private function hasSampleEnvFile(Config $config): bool
    {
        return is_file($config->cwd('.env.example'));
    }

    private function hasEnvFile(Config $config): bool
    {
        return is_file($config->cwd('.env'));
    }

    private function hasRequiredEnvFiles(Config $config): bool
    {
        return $this->hasSampleEnvFile($config) && $this->hasEnvFile($config);
    }

    public function id(): string
    {
        return "env-substitute-{$this->config->cwd()}";
    }
}
