<?php

namespace App;

use App\Config\Config;
use App\Execution\Runner;
use App\IO\IOInterface;
use App\Plugin\PluginManager;
use App\Repository\Repository;
use App\Utils\Value;
use RuntimeException;

class Factory
{
    public static function create(IOInterface $io, ?Config $config = null): Dev
    {
        $factory = new Factory();
        if(($cwd = getcwd()) === false) {
            throw new RuntimeException('Unable to retrieve the current working directory!');
        }

        $config = $config ?? Config::fromPath($cwd);
        $repository = $factory->createStepRepository();
        Value::setIO($io);
        $runner = new Runner($config, $io, $repository);
        $dev = new Dev($config, $runner, $io);

        if (! $factory->ensureGlobalDirectory($dev)) {
            throw new RuntimeException('Unable to create global path for DEV!');
        }

        $manager = $factory->createPluginManager($dev, $io);
        $dev->setPluginManager($manager);

        if ($dev->initialized) {
            $manager->loadInstalledPlugins();
        }

        return $dev;
    }

    protected function ensureGlobalDirectory(Dev $dev): bool
    {
        if (! is_dir($globalPath = $dev->config->globalPath('bin'))) {
            return mkdir($globalPath, recursive: true);
        }

        return true;
    }

    protected function createPluginManager(Dev $dev, IOInterface $io): PluginManager
    {
        return new PluginManager($dev, $io);
    }

    protected function createStepRepository(): Repository
    {
        if (app()->has(Repository::class)) {
            return app(Repository::class);
        }

        $repository = new Repository();
        app()->instance(Repository::class, $repository);

        return $repository;
    }
}
