<?php

namespace App\Plugins\Composer\Config;

use App\Contracts\ConfigInterface;
use App\Plugin\Contracts\Config;
use App\Plugin\Contracts\Step;
use App\Plugins\Composer\Steps\EnsureComposerStep;
use App\Plugins\Composer\Steps\PackagesStep;
use App\Utils\Value;

use Exception;

/**
 * @phpstan-import-type PromptArgs from Value
 *
 * @phpstan-type RawAuth array{
 *      host: string,
 *      username?: string,
 *      password?: string|PromptArgs,
 *      token?: string|PromptArgs,
 *      type?: 'basic'
 * }
 *
 * @phpstan-type RawPackage array<string, string>
 * @phpstan-type RawComposerConfig array{
 *      packages?: array<RawPackage|string|mixed>,
 *      auth?: array<int, RawAuth>
 * }
 */
class ComposerConfig implements ConfigInterface
{
    /**
     * @param RawComposerConfig $config
     * @return void
     */
    public function __construct(protected readonly array $config)
    {
    }

    /**
     * @return array<int, Step|Config>
     * @throws Exception
     */
    public function steps(): array
    {
        $steps = [new EnsureComposerStep()];
        if (isset($this->config['auth'])) {
            $steps[] = new AuthConfig($this->config['auth']);
        }

        if (isset($this->config['packages']) && ! empty($this->config['packages'])) {
            $steps[] = new PackagesStep($this->config['packages']);
        }

        return $steps;
    }
}
