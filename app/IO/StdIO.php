<?php

namespace App\IO;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StdIO implements IOInterface
{
    public function __construct(private InputInterface $input, private OutputInterface $output)
    {
    }

    public function write(string $data): void
    {
        $this->output->write($data);
    }

    public function info(string $message): void
    {
        $this->output->writeln($message);
    }

    public function error(string $message): void
    {
        $this->output->writeln("<error>$message</error>");
    }
}
