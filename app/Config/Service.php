<?php

namespace App\Config;

use Exception;

class Service
{
    public readonly string $id;

    public function __construct(public readonly Config $config)
    {
        $this->id = $config->serviceName();
    }

    /**
     * @throws Exception
     */
    public function steps(): array
    {
        return $this->config->up()->steps();
    }
}
