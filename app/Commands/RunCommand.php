<?php

namespace App\Commands;

use App\Config\Config;
use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Repository\StepRepository;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class RunCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'run {name?} {subcommand?}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Run a custom command defined in garm.yaml';

    protected readonly Config $config;

    protected readonly Runner $runner;

    /**
     * @throws UserException
     */
    public function __construct(protected readonly StepRepository $stepRepository)
    {
        parent::__construct();

        $this->config = Config::fromPath(getcwd());
        $this->runner = new Runner($this->config, $this);
    }

    /**
     * @throws UserException
     */
    public function handle(): int
    {
        $commands = $this->config->commands();

        if ($commands->isEmpty()) {
            throw new UserException('No commands found');
        }

        if (! $name = $this->argument('name')) {
            $this->registerAvailableCommands();

            return $this->call('list');
        }

        if (! $commands->has($name)) {
            throw new CommandNotFoundException("Command $name not found. Are you sure you have it configured?");
        }

        $command = collect($commands->get($name));
        $subcommands = collect($command->get('subcommands'));

        $subcommand = $this->argument('subcommand');
        if ($subcommand && ! $subcommands->has($subcommand)) {
            throw new UserException("No subcommand $subcommand found for command $name");
        }

        if ($subcommand) {
            $command = collect($subcommands->get($subcommand));
        }

        if (! $command->has('run')) {
            throw new UserException("Command $name is not configured correctly");
        }

        $this->runner->spawn($command->get('run'), $this->config->cwd())->wait()->throw();

        return self::SUCCESS;
    }

    private function registerAvailableCommands(): void
    {
        $commands = $this->config->commands();

        foreach ($commands as $name => $command) {
            $cmd = new \Illuminate\Console\Command();
            $cmd->setName($name)
                ->setDescription($command['desc'])
                ->setAliases($command['aliases'] ?? [])
                ->setLaravel($this->getLaravel());

            $this->getApplication()->add($cmd);
        }
    }
}
