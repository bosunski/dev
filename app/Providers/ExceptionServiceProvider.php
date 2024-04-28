<?php

namespace App\Providers;

use App\Exceptions\ExceptionHandler;
use App\IO\IOInterface;
use Illuminate\Contracts\Debug\ExceptionHandler as DebugExceptionHandler;
use Illuminate\Support\ServiceProvider;

class ExceptionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }

    public function register(): void
    {
        $this->registerExceptionHandler();
    }

    protected function registerExceptionHandler(): void
    {
        $defaultHandler = $this->app->make(DebugExceptionHandler::class);
        $this->app->singleton(
            DebugExceptionHandler::class,
            fn () => new ExceptionHandler($defaultHandler, $this->app->make(IOInterface::class))
        );
    }
}
