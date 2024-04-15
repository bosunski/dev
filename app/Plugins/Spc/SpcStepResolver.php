<?php

namespace App\Plugins\Spc;

use App\Dev;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;
use App\Plugins\Spc\Config\SpcConfig;

/**
 * @phpstan-import-type RawSpcConfig from SpcConfig
 */
class SpcStepResolver implements StepResolverInterface
{
    use Concerns\ResolvesEnvironment;

    public const PHP_VERSION_MAP = [
        '8.3' => 'php',
        '8.2' => 'php@8.2',
        '8.1' => 'php@8.1',
        '8.0' => 'php@8.0',
        '7.4' => 'php@7.4',
    ];

    /**
     * @var RawSpcConfig
     */
    protected array $config = [];

    public function __construct(protected readonly Dev $dev)
    {
    }

    /**
     * @param RawSpcConfig $args
     * @return Config|Step
     */
    public function resolve(mixed $args): Config | Step
    {
        assert(is_array($args), 'Spc configuration should be an array');

        /**
         * Consoder resolving enviroment and injecting envs before
         * this resolver is called. For example, because this resolve function
         * is called only when up command is run, the environment variables
         * that we inject here, won't be available when a the `serve` command runs.
         *
         * We should be able to inject the environment variables, anytime environment
         * variables are needed.
         */
        // $environment = $this->resolveEnvironmentSettings($args);
        // $this->config = array_merge($this->config, $args, ['environment' => $environment]);

        return new SpcConfig($args, $this->dev->config);
    }
}
