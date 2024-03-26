<?php

namespace App\Commands;

use App\Config\Config;
use App\Dev;
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
    protected $signature = 'run {name?} {subcommand?} {--service=}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Run a custom command defined in dev.yml';

    protected Config $config;

    protected readonly Runner $runner;

    /**
     * @throws UserException
     */
    public function __construct(protected readonly StepRepository $stepRepository, Dev $dev)
    {
        parent::__construct();

        $this->config = $dev->config;
        $this->runner = $dev->runner;
    }

    /**
     * @throws UserException
     */
    public function handle(): int
    {
        if ($service = $this->option('service')) {
            if ($this->config->services()->contains($service)) {
                $this->config = Config::fromServiceName($service);
            } else {
                throw new UserException("Service $service not found in this project. Are you sure it is registered?");
            }
        }

        $commands = $this->config->commands();

        if ($name = $this->argument('name') && $commands->isEmpty()) {
            throw new UserException('No commands found');
        }

        if (! $name = $this->argument('name')) {
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

        return $this->runner
            ->spawn($command->get('run'), $this->config->cwd())
            ->wait()
            ->exitCode();
    }
}
