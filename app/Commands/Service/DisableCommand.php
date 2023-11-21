<?php

namespace App\Commands\Service;

use App\Config\Config;
use App\Exceptions\UserException;
use App\Execution\Runner;
use LaravelZero\Framework\Commands\Command;

class DisableCommand extends Command
{
    protected $signature = 'service:disable {service?}';

    protected $description = 'Disables a registered service';

    protected readonly Config $config;

    protected readonly Runner $runner;

    /**
     * @throws UserException
     */
    public function __construct()
    {
        parent::__construct();

        $this->config = Config::fromPath(getcwd());
        $this->runner = new Runner($this->config, $this);
    }

    /**
     * @throws UserException
     */
    public function handle(): int
    {
        $services = $this->config->services(true);
        if ($services->isEmpty()) {
            $this->error('No registered services found');

            return self::INVALID;
        }

        if (! $service = $this->argument('service')) {
            $service = $this->askForService($services->toArray());
        }

        if (! $services->contains($service)) {
            $this->error("Service $service not found in configuration");

            return self::INVALID;
        }

        if (in_array($service, $this->config->settings['disabled'])) {
            $this->info("Service $service is already disabled");

            return self::SUCCESS;
        }

        $this->config->settings['disabled'][] = $service;
        $this->config->writeSettings();

        $this->info("Service $service disabled");

        return self::SUCCESS;
    }

    /**
     * @throws UserException
     */
    private function askForService(array $services): string
    {
        $service = $this->choice('Which service do you want to disable?', $services);

        if (! $service) {
            throw new UserException('No service selected');
        }

        return $service;
    }
}
