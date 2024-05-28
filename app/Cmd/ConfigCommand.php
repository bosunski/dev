<?php

namespace App\Cmd;

use App\Contracts\Command\ResolvesOwnArgs;
use App\Dev;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidArgumentException as ExceptionInvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigCommand extends Command implements ResolvesOwnArgs
{
    /**
     * @param string[] $command
     * @param bool $hasSignature
     * @param Dev $dev
     * @return void
     * @throws InvalidArgumentException
     * @throws ExceptionInvalidArgumentException
     * @throws LogicException
     */
    public function __construct(protected array $command, protected bool $hasSignature, protected Dev $dev)
    {
        $this->signature = $command['signature'];
        parent::__construct();

        $this->setDescription($command['desc'] ?? '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argString = (new ArgvInput())->__toString();
        $argString = str_replace("{$this->command['name']} ", '', $argString);
        $command = str_replace('@1', $argString, $this->command['run']);

        return (int) $this->dev->runner->spawn($command, $this->dev->config->cwd())
            ->wait()
            ->exitCode();
    }
}
