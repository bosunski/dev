<?php

namespace App\Commands;

use App\Config\Config;
use App\Dev;
use App\Exceptions\Project\ProjectNotFoundException;
use App\Exceptions\UserException;
use App\Factory;
use App\IO\IOInterface;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class RunCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'run {name?} {--project=}';

    /**
     * @var string
     */
    protected $description = 'Run a custom command defined in dev.yml';

    /**
     * @throws UserException
     */
    public function handle(Dev $dev): int
    {
        if ($project = $this->option('project')) {
            $dev = $this->resolveDev($dev, $project);
        }

        $commands = $dev->config->commands();
        if ($name = $this->argument('name') && $commands->isEmpty()) {
            throw new UserException('No commands found');
        }

        if ($commands->isEmpty()) {
            return $this->call('list');
        }

        if (! $name = $this->argument('name')) {
            $name = $this->selectCommand($dev->config, $dev->io());
        }

        if (! $command = $commands->get($name)) {
            throw new CommandNotFoundException("Command $name not found. Are you sure you have it configured?");
        }

        return $dev->runner
            ->spawn($command['run'], $dev->config->cwd())
            ->wait()
            ->exitCode() ?? 0;
    }

    protected function selectCommand(Config $config, IOInterface $io): string
    {
        return $io->select(
            'Which command do you want to run?',
            $config->commands()->map(fn ($command, $name) => $command['desc'] ?? $name),
            hint: 'Commands are defined under `commands` in dev.yml'
        );
    }

    protected function resolveDev(Dev $oldDev, string $project): Dev
    {
        if ($oldDev->config->projects()->contains($project)) {
            return Factory::create($oldDev->io(), Config::fromProjectName($project, $oldDev->config->path()));
        }

        throw new ProjectNotFoundException($project);
    }

    private function registerAvailableCommands(Config $config): void
    {
        $commands = $config->commands();
        foreach ($commands as $name => $command) {
            $cmd = new \Illuminate\Console\Command();

            $cmd->setName($name)
                ->setDescription($command['desc'] ?? '')
                ->setAliases([])
                ->setLaravel($this->getLaravel());

            $this->getApplication()?->add($cmd);
        }
    }
}
