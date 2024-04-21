<?php

namespace App\Config;

use App\Utils\Value;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @phpstan-import-type PromptArgs from Value
 */
class Env
{
    protected bool $resolved = false;

    /**
     * @var array<string, string>
     */
    protected array $prompted = [];

    /**
     * @param Collection<string, string> $env
     * @param array<string, string> $substitutions
     */
    public function __construct(protected Collection $env, protected array $substitutions = [])
    {
    }

    /**
     * @param array<string, string> $prompted
     * @return array{Collection<string, string>, array<string, string>}
     */
    public function resolve(array $prompted = []): array
    {
        if ($this->resolved) {
            return [$this->env, $this->prompted];
        }

        foreach ($this->env as $key => $value) {
            if (Value::from($value)->shouldPrompt() && array_key_exists($key, $prompted)) {
                $resolved = $prompted[$key];
                $this->prompted[$key] = $resolved;
            } else {
                $resolved = $this->resolveValue($key, $value);
            }

            $this->env[$key] = $resolved;
        }

        $this->resolved = true;

        return [$this->env, $this->prompted];
    }

    /**
     * @param string $key
     * @param string|PromptArgs $value
     * @return string
     * @throws InvalidArgumentException
     */
    protected function resolveValue(string $key, string|array $value): string
    {
        $value = Value::from($value);
        $resolved = $value->resolve(collect($this->substitutions));

        if ($value->wasPrompted()) {
            $this->prompted[$key] = $resolved;
        }

        return $resolved;
    }
}
