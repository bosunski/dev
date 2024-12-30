<?php

namespace App\Plugins\Core\Resolvers;

use App\Dev;
use App\Exceptions\UserException;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugin\StepResolverInterface;
use App\Plugins\Core\Config\MySqlConfig;
use InvalidArgumentException;

class MySqlResolver implements StepResolverInterface
{
    public function __construct(protected Dev $dev)
    {
    }

    public function resolve(mixed $args): Config|Step
    {
        if (! is_array($args) && ! is_string($args)) {
            throw new InvalidArgumentException('MySQL configuration should be an array or string!');
        }

        if (! isset($args['databases'])) {
            throw new UserException('MySQL configuration should have a databases key!');
        }

        return new MySqlConfig($args, $this->dev);
    }
}
