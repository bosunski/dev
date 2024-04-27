<?php

namespace App\Bootstrap;

use App\Providers\ExceptionServiceProvider;
use LaravelZero\Framework\Application;
use LaravelZero\Framework\Contracts\BoostrapperContract;

class ConfiguresDev implements BoostrapperContract
{
    public function bootstrap(Application $app): void
    {
        $app->register(ExceptionServiceProvider::class);
    }
}
