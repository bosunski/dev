<?php

namespace App\Utils;

use App\Exceptions\UserException;
use App\IO\IOInterface;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

/**
 * @phpstan-type PromptArgs array{
*      prompt: string,
*      label?: string,
*      placeholder?: string,
*      default?: string,
*      required?: bool,
*      validate?: string[],
*      hint?: string,
*      type?: 'password'|'text'
* }
*/
class Value
{
    protected static IOInterface $io;

    protected bool $prompted = false;

    /**
     * @param string|PromptArgs $value
     * @return void
     */
    public function __construct(protected string|array $value)
    {
    }

    /**
     * @param string|PromptArgs $value
     * @return Value
     */
    public static function from(string|array $value): Value
    {
        return new Value($value);
    }

    public static function setIO(IOInterface $io): void
    {
        self::$io = $io;
    }

    public function wasPrompted(): bool
    {
        return $this->prompted;
    }

    /**
     * @param Collection<string, string> $substitutions
     * @return string
     */
    public function resolve(?Collection $substitutions = null): string
    {
        if (is_array($this->value)) {
            return $this->value = $this->prompt($this->value);
        }

        if (! $this->value) {
            return $this->value;
        }

        $fns = [
            fn () => $this->substitute($substitutions ?? collect()),
            fn () => $this->evaluate(),
            $this->parsePrompts(...),
        ];

        array_reduce($fns, fn ($carry, $fn) => $fn(), $this->value);

        return $this->value;
    }

    /**
     * @param PromptArgs $args
     * @return string
     * @throws InvalidArgumentException
     */
    protected function prompt(array $args): string
    {
        if (! isset($args['type'])) {
            $args['type'] = 'text';
        }

        if (! in_array($args['type'], ['password', 'text'])) {
            throw new InvalidArgumentException("Unknown prompt type: {$args['type']}");
        }

        $this->prompted = true;

        return match ($args['type']) {
            'password' => self::$io->password(
                $args['prompt'],
                $args['placeholder'] ?? '',
                $args['required'] ?? true,
                null,
                $args['hint'] ?? ''
            ),
            'text' => self::$io->text(
                $args['prompt'],
                $args['placeholder'] ?? '',
                $args['default'] ?? '',
                $args['required'] ?? true,
                null,
                $args['hint'] ?? ''
            ),
        };
    }

    /**
     * @param Collection<string, string> $substitutions
     * @return string
     */
    protected function substitute(Collection $substitutions): string
    {
        assert(is_string($this->value), 'Value must be a string');

        if ($substitutions->isEmpty() || ! $this->value) {
            return $this->value;
        }

        preg_match_all('/\${([^}]*)}/', $this->value, $matches);
        foreach ($matches[1] ?? [] as $match) {
            $replacement = $substitutions->get($match);
            if (! $replacement) {
                return $this->value;
            }

            $this->value = str_replace('${' . $match . '}', $replacement, $this->value);
        }

        return $this->value;
    }

    protected function evaluate(): string
    {
        assert(is_string($this->value), 'Value must be a string');

        if (! $this->value) {
            return $this->value;
        }

        preg_match_all('/`([^`]*)`/', $this->value, $matches);
        foreach ($matches[1] ?? [] as $match) {
            $output = '';
            try {
                $output = Process::run($match, function ($_, $chunk) use (&$output): void { $output .= $chunk; })->throw()->output();
                $this->value = str_replace("`$match`", trim($output), $this->value);
            } catch (ProcessFailedException $e) {
                throw new UserException("Failed to evaluate environment variable: $this->value", "Output: $output");
            }
        }

        return $this->value;
    }

    protected function parsePrompts(): string
    {
        assert(is_string($this->value), 'Value must be a string');

        if (! $this->value) {
            return $this->value;
        }

        /**
         * We need to parse the value for any prompts and resolve them.
         * Prompts are defined like these:
         * - $PROMPT(password: Please enter your BAR key)
         * - $PROMPT(text: Please enter your FOO key)
         *
         * We need to resolve these prompts by calling the appropriate prompt function.
         */
        preg_match_all('/^\$PROMPT\(([^)]*)\)$/', $this->value, $matches);
        foreach ($matches[1] ?? [] as $match) {
            $args = explode(':', $match);
            if ($args[0] !== 'password' && $args[0] !== 'text') {
                throw new UserException("Unknown prompt type: {$args[0]}");
            }

            $args = [
                'type'         => $args[0],
                'prompt'       => $args[1] ?? '',
                'placeholder'  => $args[2] ?? '',
                'default'      => $args[3] ?? '',
                'required'     => (bool) ($args[4] ?? false),
                'validate'     => isset($args[5]) ? [$args[5]] : [],
                'hint'         => $args[6] ?? '',
            ];

            $this->value = str_replace("\$PROMPT($match)", $this->prompt($args), $this->value);
        }

        return $this->value;
    }

    public function shouldPrompt(): bool
    {
        return is_array($this->value) || preg_match('/^\$PROMPT\(([^)]*)\)$/', $this->value);
    }
}
