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
        $currentEnvs = Dotenv::parse($envContent);

        if (count($sampleEnvs) > 0) {
            $runner->io()->info('Substituting variables in .env file with discovered .env.example');
            $envContent .= "\n";
        }

        $envWasAdded = false;
        foreach ($sampleEnvs as $key => $value) {
            $exists = in_array($key, array_keys($currentEnvs));
            $insert = "$key=\"$value\"";

            /**
             * If the key doesn't exist in the .env file, we want to add it.
             */
            if (!$exists) {
                $envContent .= $insert . "\n";
                $envWasAdded = true;
                continue;
            }

            $hasValue = ! in_array($currentEnvs[$key] ?? null, ["", "null", "NULL"]);
            /**
             * If the key already exists in the .env file, and has a value, we don't want to
             * overwrite it. So, we skip it.
             */
            if (str_contains($envContent, "$key=") && $hasValue) {
                continue;
            }

            $hasSampleValue = ! in_array($value, ["", "null", "NULL"]);

            /**
             * If the key already exists in the .env file, but doesn't have a value, we want to
             * overwrite it if the sample env has a value for it.
             */
            if (! $hasValue && $hasSampleValue) {
                $envContent = preg_replace("/$key=(.*)/m", $insert, $envContent);
                $envWasAdded = true;
            }
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
