<?php

namespace App\Step;

use App\Config\Config;
use App\Config\Project;
use App\Execution\Runner;
use App\Process\Pool;
use Exception;
use Illuminate\Support\Facades\File;
use Revolt\EventLoop;

class ServeStep implements StepInterface
{
    public function __construct(private readonly string $path)
    {
    }

    public function name(): ?string
    {
        return null;
    }

    public function command(): ?string
    {
        return null;
    }

    public function checkCommand(): ?string
    {
        return null;
    }

    /**
     * @throws Exception
     */
    public function run(Runner $runner): bool
    {
        $config = Config::fromPath($this->path)->root();
        $project = new Project($config);

        $config->services();

        $pool = new Pool();

        $project->servicePool($pool);

        EventLoop::onSignal(SIGTERM, fn() => $pool->kill());
        EventLoop::onSignal(SIGINT, fn() => $pool->kill());
        if (! File::isDirectory($runner->config()->path())) {
            File::makeDirectory($runner->config()->path(), recursive: true);
        }

        File::put($runner->config()->path('garm.pid'), getmypid());
        $pool->join();

        if (File::exists($runner->config()->path('garm.pid'))) {
            File::delete($runner->config()->path('garm.pid'));
        }

        return true;
    }

    public function done(Runner $runner): bool
    {
        return false;
    }

    public function id(): string
    {
        return "serve";
    }
}
