<?php

namespace App\Cmd;

use App\Contracts\Command\ResolvesOwnArgs;
use App\Dev;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidArgumentException as ExceptionInvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-type ArrayCommand array{
 *      desc?: string,
 *      run: string|string[],
 *      signature?: string,
 *      name: string,
 *      path?: string,
 * }
 */
class ConfigCommand extends Command implements ResolvesOwnArgs
{
    /**
     * @param ArrayCommand $command
     * @param bool $hasSignature
     * @param Dev $dev
     * @return void
     * @throws InvalidArgumentException
     * @throws ExceptionInvalidArgumentException
     * @throws LogicException
     */
    public function __construct(protected array $command, protected bool $hasSignature, protected Dev $dev)
    {
        if (isset($command['signature'])) {
            $this->signature = $command['signature'];
        }

        parent::__construct();

        $this->setDescription($command['desc'] ?? '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /**
         * We will remove the command name from the arguments string
         * e.g $argString = "command @1 arg1 arg2 arg3" -> "@1 arg1 arg2 arg3"
         */
        $argString = Str::of((new ArgvInput())->__toString())->after($this->command['name'])->toString();

        /**
         * We will replace the @1 placeholder with the rest of the arguments
         * e.g. say the command is "command @1" and $argString = "arg1 arg2 arg3"
         * the final command will be "command arg1 arg2 arg3"
         */
        $command = str_replace('@1', $argString, $this->command['run']);

        return (int) $this->dev->runner->spawn($command, $this->command['path'] ?? $this->dev->config->cwd())
            ->wait()
            ->exitCode();
    }
}
