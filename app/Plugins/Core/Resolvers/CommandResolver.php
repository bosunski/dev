<?php

namespace App\Plugins\Core\Resolvers;

use App\Config\Config as DevConfig;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;
use App\Plugins\Core\Steps\CustomStep;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @phpstan-import-type Command from DevConfig
 */
class CommandResolver implements StepResolverInterface
{
    /**
     * @param Collection<string, Command> $commands
     * @return void
     */
    public function __construct(private Collection $commands)
    {
    }

    public function resolve(mixed $args): Config|Step
    {
        if (! is_string($args)) {
            throw new InvalidArgumentException('Command configuration should the name of a command!');
        }

        if (! $command = $this->commands->get($args)) {
            throw new InvalidArgumentException("Command `$args` not found in configuration!");
        }

        return new CustomStep($command);
    }
}
