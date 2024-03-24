<?php

namespace App\Cmd;

use App\Dev;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigCommand extends Command
{
    public function __construct(protected array $command, protected Dev $dev)
    {
        $this->signature = $command['signature'];
        parent::__construct();

        $this->setDescription($command['desc'] ?? '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputs = array_merge($input->getArguments(), $input->getOptions());

        $command = $this->command['run'];
        foreach ($inputs as $key => $value) {
            $command = str_replace("[\$$key]", $value, $command);
        }

        return $this->dev->runner->spawn($command, $this->dev->config->cwd())
            ->wait()
            ->exitCode();
    }
}
