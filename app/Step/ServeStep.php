<?php

namespace App\Step;

use App\Config\Project;
use App\Dev;
use App\Process\Process as AppProcessProcess;
use App\Process\ProcessOutput;
use Exception;
use Hyperf\Engine\Channel;
use Hyperf\Engine\Coroutine;
use Hyperf\Engine\Signal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Swoole\Coroutine as SwooleCoroutine;
use Swoole\Coroutine\WaitGroup;
use Swoole\Timer;

class ServeStep
{
    /**
     * @var Collection<int, AppProcessProcess>
     */
    protected Collection $processes;

    /**
     * @var Channel<bool>
     */
    protected Channel $interrupted;

    /**
     * @var array{int|false, int|false, int|false}
     */
    protected array $trapIds = [false, false, false];

    public function __construct(protected readonly Dev $dev)
    {
        $this->processes = collect();
        $this->interrupted = new Channel();
    }

    /**
     * @throws Exception
     */
    public function run(): bool
    {
        $output = new ProcessOutput();
        $project = new Project($this->dev);

        $this->storePid();

        try {
            $processes = $project->getServe();
            if ($processes->isEmpty()) {
                $this->dev->io()->dev('No processes to run. You can register processes user serve in the dev.yml file.');

                return false;
            }

            $shouldPrefixProjectName = $processes->count() > 1;
            $processes = $processes->values()->flatMap(fn (array $commands) => $commands)->map(function (array $process, int $index) use ($output, $shouldPrefixProjectName) {
                $name = $shouldPrefixProjectName ? $process['project'] . ':' . $process['name'] : $process['name'];
                $color = $this->generateRandomColor($index);

                $wrappedProcess = new AppProcessProcess(strtolower($name), $color, $output, $process['instance']);
                $output->addProcess($wrappedProcess);

                $this->processes->push($wrappedProcess);

                return $wrappedProcess;
            });

            $wg = new WaitGroup();
            /** @var Channel<bool> $done */
            $done = new Channel($processes->count());

            $processes->each(function (AppProcessProcess $process) use ($done, $wg): void {
                $process->writeOutput("\033[1mRunning...\033[0m\n");
                $wg->add();
                Coroutine::create(function () use ($process, $wg, $done): void {
                    Coroutine::defer(fn () => $wg->done());
                    Coroutine::defer(fn () => $done->push(true));

                    $process->start();
                });
            });

            $this->trapSignals();
            Coroutine::create(fn () => $this->waitForExit($done));

            $wg->wait();
            foreach ($this->trapIds as $id) {
                SwooleCoroutine::cancel($id);
            }

            return true;
        } finally {
            $this->removePid();
            $output->close();
        }
    }

    private function storePid(): void
    {
        if (! File::isDirectory($this->dev->config->path())) {
            File::makeDirectory($this->dev->config->path(), recursive: true);
        }

        if (! $pid = getmypid()) {
            throw new RuntimeException('DEV was unable to retrieve process ID for persistence.');
        }

        File::put($this->dev->config->path($this->dev->name), (string) $pid);
    }

    private function removePid(): void
    {
        if (is_file($this->dev->config->path($this->dev->name))) {
            unlink($this->dev->config->path($this->dev->name));
        }
    }

    private function trapSignals(): void
    {
        $this->trapIds = [
            Coroutine::create(function (): void {
                Signal::wait(SIGINT);
                $this->signal();
            })->getId(),
            Coroutine::create(function (): void {
                Signal::wait(SIGTERM);
                $this->signal();
            })->getId(),
            Coroutine::create(function (): void {
                Signal::wait(SIGHUP);
                $this->signal();
            })->getId(),
        ];
    }

    private function generateRandomColor(int $index): int
    {
        $colors = [2, 3, 4, 5, 6, 42, 130, 103, 129, 108];

        return $colors[$index % count($colors)];
    }

    public function id(): string
    {
        return 'serve';
    }

    public function signal(): void
    {
        $this->interrupted->push(true);
    }

    /**
     * @param Channel<bool> $done
     * @return void
     */
    protected function waitForExit(Channel $done): void
    {
        $this->waitForDoneOrInterrupt($done);

        Coroutine::create(function (): void {
            Coroutine::defer(fn () => $this->interrupted->close());

            $wg = new WaitGroup();
            $this->processes->each(function (AppProcessProcess $process) use ($wg): void {
                $wg->add();

                Coroutine::create(function (AppProcessProcess $process, WaitGroup $wg): void {
                    $process->interrupt();

                    $wg->done();
                }, $process, $wg);
            });

            $wg->wait();
        });

        $this->waitTimeoutOrInterrupt();

        $this->processes->each(fn (AppProcessProcess $process) => Coroutine::create(fn () => $process->kill()));

        $done->close();
    }

    /**
     * @param Channel<bool> $done
     * @return void
     */
    private function waitForDoneOrInterrupt(Channel $done): void
    {
        $finished = new Channel();
        Coroutine::create(fn () => $finished->push($done->pop()));
        Coroutine::create(fn () => $finished->push($this->interrupted->pop()));

        $finished->pop();
    }

    private function waitTimeoutOrInterrupt(): void
    {
        $finished = new Channel();
        $timer = Timer::after(5000, fn () => $finished->push(true));
        Coroutine::create(fn () => $finished->push($this->interrupted->pop()));

        $finished->pop();
        Timer::clear($timer);
    }
}
