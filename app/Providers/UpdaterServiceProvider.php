<?php

namespace App\Providers;

use App\Updater\PharUpdater;
use App\Updater\Updater;
use Illuminate\Support\ServiceProvider;
use LaravelZero\Framework\Components\Updater\Strategy\GithubStrategy;
use LaravelZero\Framework\Components\Updater\Strategy\StrategyInterface;
use LaravelZero\Framework\Providers\Build\Build;

class UpdaterServiceProvider extends ServiceProvider
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
        $build = $this->app->make(Build::class);

        $this->app->singleton(Updater::class, function () use ($build) {
            $updater = new PharUpdater($build->getPath(), false, PharUpdater::STRATEGY_GITHUB);

            $composer = json_decode(file_get_contents(base_path('composer.json')), true);
            $name = $composer['name'];

            $strategy = $this->app['config']->get('updater.strategy', GithubStrategy::class);

            $updater->setStrategyObject($this->app->make($strategy));

            if ($updater->getStrategy() instanceof StrategyInterface) {
                $updater->getStrategy()->setPackageName($name);
            }

            if (method_exists($updater->getStrategy(), 'setCurrentLocalVersion')) {
                $updater->getStrategy()->setCurrentLocalVersion(config('app.version'));
            }

            return new Updater($updater);
        });
    }
}