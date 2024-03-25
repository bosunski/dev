<?php

namespace App\Providers;

use App\Dev;
use App\Repository\StepRepository;
use Illuminate\Console\Signals;
use Illuminate\Support\ServiceProvider;
use LaravelZero\Framework\Application;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StepRepository::class, function (Application $app) {
            return new StepRepository($app->get(Dev::class));
        });

        Signals::resolveAvailabilityUsing(function () {
            return $this->app->runningInConsole()
                && ! $this->app->runningUnitTests()
                && extension_loaded('pcntl');
        });
    }
}
