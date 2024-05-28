<?php

namespace App\Plugins\Core\Commands;

use App\Config\Project\Definition;
use App\Contracts\Command\ResolvesOwnArgs;
use App\Dev;
use App\Exceptions\UserException;
use App\Plugins\Core\Steps\CdStep;
use App\Plugins\Core\Steps\CloneStep;
use Exception;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\ArgvInput;

class CloneCommand extends Command implements ResolvesOwnArgs
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'clone';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Clones a GitHub repository';

    /**
     * Execute the console command.
     * @return int
     * @throws Exception
     */
    public function handle(Dev $dev): int
    {
        $argv = $_SERVER['argv'] ?? [];
        $fullName = $argv[2] ?? null;

        if (! $fullName) {
            throw new UserException('Repository full name or URL must be provided');
        }

        /**
         * Let's get everything after the command and repo
         * name and pass it to the next command.
         */
        $args = array_slice($argv, 3);
        $argString = (new ArgvInput(['', ...$args]))->__toString();
        assert(is_string($fullName), new InvalidArgumentException('Repository full name must be a string'));

        $definition = new Definition($fullName);

        return $dev->runner->execute([
            new CloneStep($definition, $argString),
            new CdStep($definition),
        ]);
    }
}
