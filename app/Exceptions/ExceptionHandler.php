<?php

namespace App\Exceptions;

use App\Contracts\Exception\Printable;
use App\Contracts\Solution\ProvidesSolution;
use App\Contracts\Solution\Solution;
use App\Exceptions\Config\InvalidConfigException;
use App\IO\IOInterface;
use Illuminate\Contracts\Debug\ExceptionHandler as DebugExceptionHandler;
use Swoole\ExitException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ExceptionHandler implements DebugExceptionHandler
{
    public function __construct(protected DebugExceptionHandler $defaultHandler, protected IOInterface $io)
    {
    }

    public function report(Throwable $e): void
    {
        $this->defaultHandler->report($e);
    }

    public function shouldReport(Throwable $e): bool
    {
        return $this->defaultHandler->shouldReport($e);
    }

    public function render($request, Throwable $e): Response
    {
        return $this->defaultHandler->render($request, $e);
    }

    public function renderForConsole($output, Throwable $e): void
    {
        try {
            if ($e instanceof UserException) {
                $this->processUserExceptions($e);

                return;
            }

            if ($e instanceof ExitException) {
                $this->write('<bg=red;options=bold> DEV </> Exited Unexpectedly');

                return;
            }

            $this->defaultHandler->renderForConsole($output, $e);
        } finally {
            if ($e instanceof ProvidesSolution) {
                $this->renderSolution($e->solution());
            }
        }
    }

    protected function processUserExceptions(UserException $e): void
    {
        $message = $e instanceof Printable ? $e->print() : $e->getMessage();
        $this->write("<bg=red;options=bold> DEV </> $message");

        if ($e instanceof InvalidConfigException) {
            $this->write($e->getSourceHighlight());
        }
    }

    protected function renderSolution(Solution $solution): void
    {
        $title = $solution->title();
        $description = $solution->description();
        $links = $solution->links();

        $description = trim((string) preg_replace("/\n/", "\n    ", $description));

        $this->write(sprintf(
            '<fg=cyan;options=bold>i</>   <fg=default;options=bold>%s</>: %s %s',
            rtrim($title, '.'),
            $description,
            implode(', ', array_map(function (string $link) {
                return sprintf("\n      <fg=gray>%s</>", $link);
            }, $links))
        ));
    }

    private function write(string $message, bool $break = true): self
    {
        if ($break) {
            $this->io->writeln('');
        }

        $this->io->writeln($message);

        return $this;
    }
}
