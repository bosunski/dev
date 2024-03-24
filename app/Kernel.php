<?php

namespace App;

use App\Cmd\ConfigCommand;
use App\IO\StdIO;
use App\Plugin\Capability\Capabilities;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;
use LaravelZero\Framework\Kernel as LaravelZeroKernel;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Kernel extends LaravelZeroKernel
{
    protected ?Dev $dev = null;

    public function handle($input, $output = null)
    {
        $this->resolveDev($input, $output);

        return parent::handle($input, $output);
    }

    protected function resolveDev(?InputInterface $input = null, ?OutputInterface $output = null): Dev
    {
        if ($this->dev) {
            return $this->dev;
        }

        $this->dev = Factory::create(new StdIO($input ?? new ArgvInput(), $output ?? new ConsoleOutput()));

        $this->app->instance(Dev::class, $this->dev);

        return $this->dev;
    }

    public function commands(): void
    {
        $this->resolveDev();

        $this->addPluginCommands();
        $this->addConfigCommands();

        parent::commands();
    }

    protected function addPluginCommands(): void
    {
        $manager = $this->dev->getPluginManager();
        $commands = [];
        foreach ($manager->getPluginCapabilities(Capabilities::Command, [$this->dev, $this->dev->io()]) as $capability) {
            $newCommands = $capability->getCommands();
            foreach ($newCommands as $command) {
                if (! $command instanceof Command) {
                    throw new \UnexpectedValueException('Plugin capability ' . get_class($capability) . ' returned an invalid value, we expected an array of Composer\Command\BaseCommand objects');
                }
            }

            $commands = array_merge($commands, $newCommands);
        }

        $defaultCommands = config('commands.add');
        $commands = array_merge($commands, $defaultCommands);

        config(['commands.add' => $commands]);
    }

    protected function addConfigCommands(): void
    {
        $manager = $this->dev->getPluginManager();
        $commands = collect();
        foreach ($manager->getPluginCapabilities(Capabilities::Command, [$this->dev, $this->dev->io()]) as $capability) {
            $newCommands = $capability->getConfigCommands();
            foreach ($newCommands as $name => $command) {
                $commands[$name] = $command;
            }
        }

        $this->addConfigCommand($commands->merge($this->dev->config->commands()));
    }

    protected function addConfigCommand(Collection $commands): void
    {
        $commands = $commands->map(function (array $command, string $name) {
            $signature = $command['signature'] ?? null;
            $signature = $signature ? "$name $signature" : $name;
            $command['signature'] = $signature;

            return new ConfigCommand($command, $this->dev);
        });

        $existingCommands = config('commands.add');
        config(['commands.add' => $commands->merge($existingCommands)->all()]);
    }
}
