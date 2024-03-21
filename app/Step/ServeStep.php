<?php

namespace App\Step;

use App\Config\Config;
use App\Config\Project;
use App\Execution\Runner;
use App\Process\Process as AppProcessProcess;
use App\Process\ProcessOutput;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\WaitGroup;
use Swoole\Timer;

use function Swoole\Coroutine\defer;
use function Swoole\Coroutine\go;

class ServeStep implements StepInterface
{
    protected Collection $processes;

    protected Channel $done;

    protected Channel $interrupted;

    protected array $trapIds = [];

    public function __construct(private readonly string $path)
    {
        $this->processes = collect();
        $this->done = new Channel();
        $this->interrupted = new Channel();
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
        $processes = collect($config->getServe());
        $output = new ProcessOutput($runner->io());
        $project = new Project($config);

        try {
            if (! File::isDirectory($runner->config()->path())) {
                File::makeDirectory($runner->config()->path(), recursive: true);
            }

            File::put($runner->config()->path($name = config('app.name')), getmypid());

            $ps = $project->getServe();
            $shouldPrefixProjectName = $ps->count() > 1;
            $processes = $ps->values()->flatMap(fn ($commands) => $commands)->map(function (array $process, int $index) use ($output, $shouldPrefixProjectName) {
                $name = $shouldPrefixProjectName ? $process['project'] . ':' . $process['name'] : $process['name'];
                $color = $this->generateRandomColor($index);
                $p = new AppProcessProcess($name, $process['command'], $color, $process['env'], $output, $this);
                $output->addProcess($p);

                $this->processes->push($p);

                return $p;
            });

            $wg = new WaitGroup();
            $done = new Channel($processes->count());

            $processes->each(function (AppProcessProcess $process) use ($done, $wg): void {
                $process->writeOutput("\033[1mRunning...\033[0m\n");
                $wg->add();
                Coroutine::create(function () use ($process, $wg, $done): void {
                    defer(fn () => $wg->done());
                    defer(fn () => $done->push(true));

                    $process->start();
                });
            });

            $this->trap();

            go(fn () => $this->waitForExit($done));

            $wg->wait();
            foreach ($this->trapIds as $id) {
                Coroutine::cancel($id);
            }

            return true;
        } finally {
            if (File::exists($runner->config()->path($name))) {
                File::delete($runner->config()->path($name));
            }
        }
    }

    private function trap(): void
    {
        $this->trapIds = [
            go(function (): void {
                while(Coroutine::waitSignal(SIGINT)) {
                    $this->signal();
                }
            }),
            go(function (): void {
                while(Coroutine::waitSignal(SIGTERM)) {
                    $this->signal();
                }
            }),
            go(function (): void {
                while(Coroutine::waitSignal(SIGHUP)) {
                    $this->signal();
                }
            }),
        ];
    }

    private function generateRandomColor(int $index): string
    {
        $colors = [2, 3, 4, 5, 6, 42, 130, 103, 129, 108];

        return $colors[$index % count($colors)];
    }

    public function done(Runner $runner): bool
    {
        return false;
    }

    public function id(): string
    {
        return 'serve';
    }

    public function signal(): void
    {
        $this->interrupted->push(true);
    }

    protected function waitForDoneOrInterrupt(Channel $done): void
    {
        $finished = new Channel();
        go(fn () => $finished->push($done->pop()));
        go(fn () => $finished->push($this->interrupted->pop()));

        $finished->pop();
    }

    protected function waitTimeoutOrInterrupt(): void
    {
        $finished = new Channel();
        $timer = swoole_timer_after(5, fn () => $finished->push(true));
        go(fn () => $finished->push($this->interrupted->pop()));

        $finished->pop();
        Timer::clear($timer);
    }

    protected function waitForExit(Channel $done): void
    {
        $this->waitForDoneOrInterrupt($done);

        $this->processes->each(fn (AppProcessProcess $process) => go(fn () => $process->interrupt()));

        $this->waitTimeoutOrInterrupt();

        $this->processes->each(fn (AppProcessProcess $process) => go(fn () => $process->kill()));

        $this->done->close();
        $this->interrupted->close();
    }
}
