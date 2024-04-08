<?php

namespace App;

use App\Config\Config;
use App\Execution\Runner;
use App\IO\IOInterface;
use App\Plugin\PluginManager;
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
        $runner = new Runner($config, $io);
        $dev = new Dev($config, $runner, $io);

        $manager = $factory->createPluginManager($dev, $io);
        $dev->setPluginManager($manager);

        $manager->loadInstalledPlugins();

        return $dev;
    }

    protected function createPluginManager(Dev $dev, IOInterface $io): PluginManager
    {
        return new PluginManager($dev, $io);
    }
}
