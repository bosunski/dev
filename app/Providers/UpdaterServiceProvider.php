<?php

namespace App\Providers;

use App\Updater\PharUpdater;
use App\Updater\Updater;
use Illuminate\Support\ServiceProvider;
use LaravelZero\Framework\Components\Updater\Strategy\GithubStrategy;
use LaravelZero\Framework\Components\Updater\Strategy\StrategyInterface;
use LaravelZero\Framework\Providers\Build\Build;
use Phar;
use RuntimeException;

class UpdaterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
    }

    public function register(): void
    {
        $build = $this->app->make(Build::class);

        $this->app->singleton(Updater::class, function () use ($build) {
            $updater = new PharUpdater($build->getPath(), false, PharUpdater::STRATEGY_GITHUB);
            $composerJsonContent = file_get_contents(base_path('composer.json'));
            if ($composerJsonContent === false) {
                throw new RuntimeException('composer.json not found');
            }

            $composer = json_decode($composerJsonContent, true);
            if ($composer === null || ! is_array($composer) || ! isset($composer['name'])) {
                throw new RuntimeException('composer.json is not valid');
            }

            $name = $composer['name'];
            /** @var string $strategy */
            $strategy = $this->app->make('config')->get('updater.strategy', GithubStrategy::class);
            $updater->setStrategyObject($this->app->make($strategy));
            $stg = $updater->getStrategy();

            if ($stg instanceof StrategyInterface) {
                $stg->setPackageName($name);

                if (method_exists($stg, 'setCurrentLocalVersion')) {
                    if (! Phar::running()) {
                        $version = 'development';
                    }

                    $stg->setCurrentLocalVersion($version ?? config('app.version'));
                }
            }

            return new Updater($updater);
        });
    }
}
