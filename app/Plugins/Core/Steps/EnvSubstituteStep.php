<?php

namespace App\Plugins\Core\Steps;

use App\Config\Config;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Dotenv\Dotenv;

class EnvSubstituteStep implements Step
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
        if ($this->shouldCopyEnv($runner->config())) {
            copy($runner->config()->cwd('.env.example'), $runner->config()->cwd('.env'));
        }

        if (! $this->hasSampleEnvFile($runner->config()) || ! $this->hasEnvFile($runner->config())) {
            return true;
        }

        $sampleEnvContent = file_get_contents($runner->config()->cwd('.env.example'));
        $envContent = file_get_contents($runner->config()->cwd('.env'));
        $envContent = $envContent === false ? '' : $envContent;
        $sampleEnvContent = $sampleEnvContent === false ? '' : $sampleEnvContent;

        // ToDo: Handle errors when parsing .env files
        $sampleEnvs = $sampleEnvContent ? Dotenv::parse($sampleEnvContent) : [];
        $currentEnvs = $envContent ? Dotenv::parse($envContent) : [];

        /**
         * If the .env file doesn't end with a new line, we want to add one before adding
         * new envs to it. This is to ensure that the file is properly formatted.
         * We will also wwant to make sure this is only done if there are envs to add.
         */
        if (count($sampleEnvs) > 0 && ! str_ends_with($envContent, PHP_EOL)) {
            $envContent .= PHP_EOL;
        }

        $envWasAdded = false;
        foreach ($sampleEnvs as $key => $value) {
            $exists = in_array($key, array_keys($currentEnvs));
            $insert = "$key=\"$value\"";

            /**
             * If the key doesn't exist in the .env file, we want to add it.
             */
            if (! $exists) {
                $envContent .= $insert . PHP_EOL;
                $envWasAdded = true;

                continue;
            }

            $hasValue = ! in_array($currentEnvs[$key] ?? null, ['', 'null', 'NULL']);
            /**
             * If the key already exists in the .env file, and has a value, we don't want to
             * overwrite it. So, we skip it.
             */
            if (str_contains($envContent, "$key=") && $hasValue) {
                continue;
            }

            $hasSampleValue = ! in_array($value, ['', 'null', 'NULL']);

            /**
             * If the key already exists in the .env file, but doesn't have a value, we want to
             * set a value for it if the sample env has a value for it.
             */
            if (! $hasValue && $hasSampleValue) {
                $replace = preg_replace("/$key=(.*)/m", $insert, $envContent);
                if ($replace) {
                    $envContent = $replace;
                    $envWasAdded = true;
                }
            }
        }

        /**
         * Envs in the config file takes precedence over all other envs.
         * So, we want to add them to the .env file if they don't already exist.
         * We also want to replace the value of the env if it already exists.
         */
        foreach ($runner->config()->envs() as $key => $value) {
            $insert = "$key=\"$value\"";

            if (! preg_match("/$key=(.*)/m", $envContent)) {
                $envContent .= $insert;
                $envWasAdded = true;
            } else {
                $replace = preg_replace("/$key=(.*)/m", $insert, $envContent);
                if ($replace) {
                    $envContent = $replace;
                    $envWasAdded = true;
                }
            }
        }

        if ($envWasAdded) {
            // A little cleanup to ensure there are no more than 2 newlines in the file
            // It's not a big deal, but a cosmetic change to make the file look better
            $envContent = (string) preg_replace("/\n{3,}/", PHP_EOL . PHP_EOL, $envContent);

            // Ensure there is a newline at the end of the file
            if (substr($envContent, -1) !== PHP_EOL) {
                $envContent .= PHP_EOL;
            }

            file_put_contents($runner->config()->cwd('.env'), $envContent);
        }

        return true;
    }

    private function shouldCopyEnv(Config $config): bool
    {
        return ! is_file($config->cwd('.env'))
            && is_file($config->cwd('.env.example'));
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

    public function id(): string
    {
        return "env-substitute-{$this->config->projectName()}";
    }
}
