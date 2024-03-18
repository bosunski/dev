<?php

namespace App\Commands;

use App\Config\Config;
use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Step\ServeStep;
use Exception;
use LaravelZero\Framework\Commands\Command;
use Swoole\Runtime;
use Throwable;

use function Swoole\Coroutine\run;

class ServeCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'serve';

    protected $aliases = ['s'];

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Start the application services';

    /**
     * Execute the console command.
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function handle(): int
    {
        $resultCode = 0;
        Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
        $lastError = null;
        run(function () use (&$resultCode, &$lastError): void {
            try {
                $config = new Config(getcwd(), []);
                $runner = new Runner($config, $this);
                $serveStep = new ServeStep($config->cwd());

                $resultCode = $runner->execute([$serveStep], true);
            } catch (UserException $e) {
                $this->components->error($e->getMessage());
                $resultCode = 1;
            } catch (Throwable $e) {
                $lastError = $e;
                $resultCode = 1;
            }
        });

        if ($lastError !== null) {
            $this->components->error($lastError->getMessage());
        }

        return $resultCode;
    }
}
