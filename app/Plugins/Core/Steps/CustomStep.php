<?php

namespace App\Plugins\Core\Steps;

use App\Config\Config;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type Script from Config
 */
class CustomStep implements Step
{
    /**
     * @param Script $config
     * @return void
     */
    public function __construct(private readonly array $config)
    {
    }

    public function name(): string
    {
        return $this->config['desc'] ?? '';
    }

    /**
     * @return string|string[]
     */
    public function command(): string|array
    {
        return $this->config['run'];
    }

    public function checkCommand(): ?string
    {
        return $this->config['met?'] ?? null;
    }

    public function run(Runner $runner): bool
    {
        if ($this->checkCommand() && ! $this->command()) {
            return false;
        }

        return $runner->exec($this->command(), $runner->config()->cwd());
    }

    public function done(Runner $runner): bool
    {
        if (! ($command = $this->checkCommand())) {
            return false;
        }

        return $runner->exec($command, $runner->config()->cwd());
    }

    public function id(): string
    {
        return Str::random(10);
    }
}
