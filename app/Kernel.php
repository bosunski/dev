<?php

namespace App;

use App\Bootstrap\ConfiguresDev;
use App\Cmd\ConfigCommand;
use App\Config\Config;
use App\Contracts\Command\ResolvesOwnArgs;
use App\Plugin\Capability\CommandProvider;
use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;
use LaravelZero\Framework\Kernel as LaravelZeroKernel;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use UnexpectedValueException;

/**
 * @phpstan-import-type Command from Config as RawConfigCommand
 */
class Kernel extends LaravelZeroKernel
{
    protected Dev $dev;

    public function __construct(Application $app, Dispatcher $events)
    {
        parent::__construct($app, $events);
    }

    public function handle($input, $output = null): int
    {
        return parent::handle($this->ensureRunCommand($input), $output);
    }

    public function ensureRunCommand(InputInterface $input): InputInterface
    {
        $this->bootstrap();
        $application = $this->getArtisan();

        try {
            $commandName = $input->getFirstArgument();
            if (! $commandName) {
                return $input;
            }

            $command = $application->find($commandName);
            if (! $command instanceof ResolvesOwnArgs) {
                return $input;
            }

            /**
             * If command wants to resolve its own arguments,
             * we will return a new ArgvInput instance with
             * the command name only.
             *
             * We will pass an empty string as the first argument
             * because ArgvInput will remove the first argument
             * as it is assumed to be the input file name.
             */
            return new ArgvInput(['', $commandName], $application->getDefinition());
        } catch(CommandNotFoundException $e) {
            // if command is not found, we will pass
            return $input;
        }
    }

    public function commands(): void
    {
        Artisan::starting(function (Artisan $artisan): void {
            $dev = app(Dev::class);
            $this->addPluginCommands($artisan, $dev);
            $this->addConfigCommands($artisan, $dev);
        });

        parent::commands();
    }

    protected function addPluginCommands(Artisan $artisan, Dev $dev): void
    {
        $manager = $dev->getPluginManager();
        $commands = [];
        foreach ($manager->getPluginCapabilities(CommandProvider::class, ['dev' => $dev, 'io' => $dev->io()]) as $capability) {
            $newCommands = $capability->getCommands();
            foreach ($newCommands as $command) {
                if (! $command instanceof Command) {
                    throw new UnexpectedValueException('Plugin capability ' . get_class($capability) . ' returned an invalid value, we expected an array of App\Command\BaseCommand objects');
                }
            }

            $commands = array_merge($commands, $newCommands);
        }

        $artisan->resolveCommands($commands);
    }

    protected function addConfigCommands(Artisan $artisan, Dev $dev): void
    {
        $manager = $dev->getPluginManager();
        $commands = collect();
        foreach ($manager->getPluginCapabilities(CommandProvider::class, ['dev' => $dev, 'io' => $dev->io()]) as $capability) {
            $newCommands = $capability->getConfigCommands();
            foreach ($newCommands as $name => $command) {
                $commands[$name] = $command;
            }
        }

        $this->addConfigCommand($commands->merge($dev->config->commands()), $artisan, $dev);
    }

    /**
     * @param Collection<string, RawConfigCommand> $commands
     * @param Artisan $artisan
     * @return void
     * @throws BindingResolutionException
     */
    protected function addConfigCommand(Collection $commands, Artisan $artisan, Dev $dev): void
    {
        $commands = $commands->map(function (array $command, string $name) use ($dev): ConfigCommand {
            $signature = $command['signature'] ?? null;
            $hasSignature = $signature !== null;
            $signature = $hasSignature ? "$name $signature" : $name;
            $command['signature'] = $signature;
            $command['name'] = $name;

            return new ConfigCommand($command, $hasSignature, $dev);
        });

        $artisan->resolveCommands($commands->toArray());
    }

    /**
     * @return array<class-string>
     */
    protected function bootstrappers(): array
    {
        return array_merge(parent::bootstrappers(), [
            ConfiguresDev::class,
        ]);
    }
}
