<?php

namespace App\IO;

use Closure;
use Illuminate\Support\Collection;
use Laravel\Prompts\Progress;

interface IOInterface
{
    public function write(string $data): void;

    public function writeln(string $data): void;

    /**
     * Prompt the user for text input.
     */
    public function text(string $label, string $placeholder = '', string $default = '', bool|string $required = false, mixed $validate = null, string $hint = ''): string;

    /**
     * Prompt the user for input, hiding the value.
     */
    public function password(string $label, string $placeholder = '', bool|string $required = false, mixed $validate = null, string $hint = ''): string;

    /**
     * Prompt the user to select an option.
     *
     * @param  array<int|string, string>|Collection<int|string, string>  $options
     * @param  true|string  $required
     */
    public function select(string $label, array|Collection $options, int|string|null $default = null, int $scroll = 5, mixed $validate = null, string $hint = '', bool|string $required = true): int|string;

    /**
     * Prompt the user to select multiple options.
     *
     * @param  array<int|string, string>|Collection<int|string, string>  $options
     * @param  array<int|string>|Collection<int, int|string>  $default
     * @return array<int|string>
     */
    public function multiselect(string $label, array|Collection $options, array|Collection $default = [], int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = 'Use the space bar to select options.'): array;

    /**
     * Prompt the user to confirm an action.
     */
    public function confirm(string $label, bool $default = true, string $yes = 'Yes', string $no = 'No', bool|string $required = false, mixed $validate = null, string $hint = ''): bool;

    /**
     * Prompt the user to continue or cancel after pausing.
     */
    public function pause(string $message = 'Press enter to continue...'): bool;

    /**
     * Prompt the user for text input with auto-completion.
     *
     * @param  array<string>|Collection<int, string>|Closure(string): array<string>  $options
     */
    public function suggest(string $label, array|Collection|Closure $options, string $placeholder = '', string $default = '', int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = ''): string;

    /**
     * Allow the user to search for an option.
     *
     * @param  Closure(string): array<int|string, string>  $options
     * @param  true|string  $required
     */
    public function search(string $label, Closure $options, string $placeholder = '', int $scroll = 5, mixed $validate = null, string $hint = '', bool|string $required = true): int|string;

    /**
     * Allow the user to search for multiple option.
     *
     * @param  Closure(string): array<int|string, string>  $options
     * @return array<int|string>
     */
    public function multisearch(string $label, Closure $options, string $placeholder = '', int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = 'Use the space bar to select options.'): array;

    /**
     * Render a spinner while the given callback is executing.
     *
     * @template TReturn of mixed
     *
     * @param  \Closure(): TReturn  $callback
     * @return TReturn
     */
    public function spin(Closure $callback, string $message = ''): mixed;

    /**
     * Display a note.
     */
    public function note(string $message, ?string $type = null): void;

    /**
     * Display an error.
     */
    public function error(string $message): void;

    /**
     * Display a warning.
     */
    public function warning(string $message): void;

    /**
     * Display an alert.
     */
    public function alert(string $message): void;

    /**
     * Display an informational message.
     */
    public function info(string $message): void;

    /**
     * Display an introduction.
     */
    public function intro(string $message): void;

    /**
     * Display a closing message.
     */
    public function outro(string $message): void;

    /**
     * Display a table.
     *
     * @param  array<int, string|array<int, string>>|Collection<int, string|array<int, string>>  $headers
     * @param  array<int, array<int, string>>|Collection<int, array<int, string>>  $rows
     */
    public function table(array|Collection $headers = [], array|Collection|null $rows = null): void;

    /**
     * Display a progress bar.
     *
     * @template TSteps of iterable<mixed>|int
     * @template TReturn
     *
     * @param  TSteps  $steps
     * @param  ?Closure((TSteps is int ? int : value-of<TSteps>), Progress<TSteps>): TReturn  $callback
     * @return ($callback is null ? Progress<TSteps> : array<TReturn>)
     */
    public function progress(string $label, iterable|int $steps, ?Closure $callback = null, string $hint = ''): array|Progress;
}
