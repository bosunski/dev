<?php

namespace App\Plugins\Composer\Config;

use App\Contracts\ConfigInterface;
use App\Plugins\Composer\Steps\PackagesStep;
use Exception;

class ComposerConfig implements ConfigInterface
{
    /**
     * @param array{
     *      packages?: string[],
     *      auth?: array<int, array{host: string, type: string, username: string, password: string}>
     * } $config
     * @return void
     */
    public function __construct(protected readonly array $config)
    {
    }

    /**
     * @throws Exception
     */
    public function steps(): array
    {
        $steps = [];
        if (isset($this->config['auth'])) {
            $step[] = new AuthConfig($this->config['auth']);
        }

        if (isset($this->config['packages'])) {
            $step[] = new PackagesStep($this->config['packages']);
        }

        return $steps;
    }
}
