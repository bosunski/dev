<?php

namespace App\Bootstrap;

use App\Dev;
use App\Factory;
use App\IO\IOInterface;
use App\Providers\ExceptionServiceProvider;
use LaravelZero\Framework\Application;

class ConfiguresDev
{
    public function bootstrap(Application $app): void
    {
        $app->register(ExceptionServiceProvider::class);
        $this->resolveDev($app);
    }

    protected function resolveDev(Application $app): void
    {
        $app->singleton(
            Dev::class,
            fn () => Factory::create(app(IOInterface::class))
        );
    }
}
