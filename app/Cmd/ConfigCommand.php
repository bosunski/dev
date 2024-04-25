<?php

namespace App\Cmd;

use App\Dev;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidArgumentException as ExceptionInvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigCommand extends Command
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
        $inputs = $this->hasSignature ? array_merge($input->getArguments(), $input->getOptions()) : $input->getArgument('args');

        $command = $this->command['run'];
        foreach ($inputs as $key => $value) {
            $command = str_replace("[\$$key]", $value, $command);
        }

        return $this->dev->runner->spawn($command, $this->dev->config->cwd())
            ->wait()
            ->exitCode();
    }
}
