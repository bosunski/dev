<?php

namespace App\Providers;

use App\Repository\StepRepository;
use Illuminate\Support\ServiceProvider;

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
        $this->app->singleton(StepRepository::class, function () {
            return new StepRepository();
        });
    }
}
