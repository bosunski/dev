<?php

namespace App\Plugins\Valet;

use App\Dev;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;
use App\Plugins\Valet\Config\ValetConfig;

class ValetStepResolver implements StepResolverInterface
{
    use Concerns\ResolvesEnvironment;

    public const PHP_VERSION_MAP = [
        '8.3' => 'php',
        '8.2' => 'php@8.2',
        '8.1' => 'php@8.1',
        '8.0' => 'php@8.0',
        '7.4' => 'php@7.4',
    ];

    protected array $config = [];

    public function __construct(protected readonly Dev $dev)
    {
    }

    public function name(): string
    {
        return 'valet';
    }

    /**
     * @param mixed $args
     * @return Config|Step
     */
    public function resolve(mixed $args): Config | Step
    {
        /**
         * Consoder resolving enviroment and injecting envs before
         * this resolver is called. For example, because this resolve function
         * is called only when up command is run, the environment variables
         * that we inject here, won't be available when a the `serve` command runs.
         *
         * We should be able to inject the environment variables, anytime environment
         * variables are needed.
         */
        $environment = $this->resolveEnvironmentSettings($args);
        $this->config = array_merge($this->config, $args, ['environment' => $environment]);

        return new ValetConfig($args, $environment);
    }
}
