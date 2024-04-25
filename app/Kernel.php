<?php

namespace App;

use App\Cmd\ConfigCommand;
use App\Config\Config;
use App\IO\StdIO;
use App\Plugin\Capability\CommandProvider;
use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;
use LaravelZero\Framework\Kernel as LaravelZeroKernel;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-import-type Command from Config as RawConfigCommand
 */
class Kernel extends LaravelZeroKernel
{
    protected Dev $dev;

    public function __construct(
        \Illuminate\Contracts\Foundation\Application $app,
        \Illuminate\Contracts\Events\Dispatcher $events
    ) {
        parent::__construct($app, $events);

        $this->dev = $this->resolveDev();
    }

    public function handle($input, $output = null)
    {
        return parent::handle($input, $output);
    }

    protected function resolveDev(?InputInterface $input = null, ?OutputInterface $output = null): Dev
    {
        $this->app->instance(
            Dev::class,
            $dev = Factory::create(new StdIO($input ?? new ArgvInput(), $output ?? new ConsoleOutput()))
        );

        return $dev;
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
        foreach ($manager->getPluginCapabilities(CommandProvider::class, ['dev' => $this->dev, 'io' => $this->dev->io()]) as $capability) {
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
        foreach ($manager->getPluginCapabilities(CommandProvider::class, ['dev' => $this->dev, 'io' => $this->dev->io()]) as $capability) {
            $newCommands = $capability->getConfigCommands();
            foreach ($newCommands as $name => $command) {
                $commands[$name] = $command;
            }
        }

        $this->addConfigCommand($commands->merge($this->dev->config->commands()), $artisan);
    }

    /**
     * @param Collection<string, RawConfigCommand> $commands
     * @param Artisan $artisan
     * @return void
     * @throws BindingResolutionException
     */
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
