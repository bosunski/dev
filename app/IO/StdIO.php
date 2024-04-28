<?php

namespace App\IO;

use Closure;
use Illuminate\Support\Collection;
use Laravel\Prompts\Progress;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\alert;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multisearch;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\pause;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class StdIO implements IOInterface
{
    public function __construct(private InputInterface $input, private OutputInterface $output)
    {
    }

    public function getInput(): InputInterface
    {
        return $this->input;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function text(string $label, string $placeholder = '', string $default = '', bool|string $required = false, mixed $validate = null, string $hint = ''): string
    {
        return text($label, $placeholder, $default, $required, $validate, $hint);
    }

    public function password(string $label, string $placeholder = '', bool|string $required = false, mixed $validate = null, string $hint = ''): string
    {
        return password($label, $placeholder, $required, $validate, $hint);
    }

    public function select(string $label, array|Collection $options, null|int|string $default = null, int $scroll = 5, mixed $validate = null, string $hint = '', bool|string $required = true): int|string
    {
        return select($label, $options, $default, $scroll, $validate, $hint, $required);
    }

    public function multiselect(string $label, array|Collection $options, array|Collection $default = [], int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = 'Use the space bar to select options.'): array
    {
        return multiselect($label, $options, $default, $scroll, $required, $validate, $hint);
    }

    public function confirm(string $label, bool $default = true, string $yes = 'Yes', string $no = 'No', bool|string $required = false, mixed $validate = null, string $hint = ''): bool
    {
        return confirm($label, $default, $yes, $no, $required, $validate, $hint);
    }

    public function pause(string $message = 'Press enter to continue...'): bool
    {
        return pause($message);
    }

    public function suggest(string $label, array|Collection|Closure $options, string $placeholder = '', string $default = '', int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = ''): string
    {
        return suggest($label, $options, $placeholder, $default, $scroll, $required, $validate, $hint);
    }

    public function search(string $label, Closure $options, string $placeholder = '', int $scroll = 5, mixed $validate = null, string $hint = '', bool|string $required = true): int|string
    {
        return search($label, $options, $placeholder, $scroll, $validate, $hint, $required);
    }

    public function multisearch(string $label, Closure $options, string $placeholder = '', int $scroll = 5, bool|string $required = false, mixed $validate = null, string $hint = 'Use the space bar to select options.'): array
    {
        return multisearch($label, $options, $placeholder, $scroll, $required, $validate, $hint);
    }

    public function spin(Closure $callback, string $message = ''): mixed
    {
        return spin($callback, $message);
    }

    public function note(string $message, ?string $type = null): void
    {
        note($message, $type);
    }

    public function warning(string $message): void
    {
        warning($message);
    }

    public function alert(string $message): void
    {
        alert($message);
    }

    public function intro(string $message): void
    {
        intro($message);
    }

    public function outro(string $message): void
    {
        outro($message);
    }

    public function table(array|Collection $headers = [], null|array|Collection $rows = null): void
    {
        table($headers, $rows);
    }

    public function progress(string $label, iterable|int $steps, ?Closure $callback = null, string $hint = ''): array|Progress
    {
        return progress($label, $steps, $callback, $hint);
    }

    public function write(string $data): void
    {
        $this->output->write($data);
    }

    public function writeln(string $data): void
    {
        $this->output->write($data . PHP_EOL);
    }

    public function info(string $message): void
    {
        info($message);
    }

    public function error(string $message): void
    {
        error($message);
    }
}
