<?php

namespace App;

use App\Config\Config;
use App\Execution\Runner;
use App\IO\IOInterface;
use App\Plugin\PluginManager;

class Factory
{
    public static function create(IOInterface $io, ?Config $config = null): Dev
    {
        $factory = new static();
        $config = $config ?? Config::fromPath(getcwd());
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
