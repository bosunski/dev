<?php

namespace App;

use App\Cmd\ConfigCommand;
use App\IO\StdIO;
use App\Plugin\Capability\Capabilities;
use Illuminate\Console\Application as Artisan;
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

        Artisan::starting(function (Artisan $artisan): void {
            $this->addPluginCommands($artisan);
            $this->addConfigCommands($artisan);
        });

        parent::commands();
    }

    protected function addPluginCommands(Artisan $artisan): void
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

        $artisan->resolveCommands($commands);
    }

    protected function addConfigCommands(Artisan $artisan): void
    {
        $manager = $this->dev->getPluginManager();
        $commands = collect();
        foreach ($manager->getPluginCapabilities(Capabilities::Command, [$this->dev, $this->dev->io()]) as $capability) {
            $newCommands = $capability->getConfigCommands();
            foreach ($newCommands as $name => $command) {
                $commands[$name] = $command;
            }
        }

        $this->addConfigCommand($commands->merge($this->dev->config->commands()), $artisan);
    }

    protected function addConfigCommand(Collection $commands, Artisan $artisan): void
    {
        $commands = $commands->map(function (array $command, string $name) {
            $signature = $command['signature'] ?? null;
            $hasSignature = $signature !== null;
            $signature = $hasSignature ? "$name $signature" : "$name {args?*}";
            $command['signature'] = $signature;

            return new ConfigCommand($command, $hasSignature, $this->dev);
        });

        $artisan->resolveCommands($commands->toArray());
    }
}
