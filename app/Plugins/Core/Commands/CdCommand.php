<?php

namespace App\Plugins\Core\Commands;

use App\Dev;
use App\Plugins\Core\Steps\CdStep;
use Exception;
use LaravelZero\Framework\Commands\Command;

class CdCommand extends Command
{
    private const KNOWN_SOURCES = [
        'github'    => 'github.com',
        'gitlab'    => 'gitlab.com',
        'bitbucket' => 'bitbucket.org',
    ];

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'cd {repo} {--source=}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Change directory to a project repo';

    /**
     * Execute the console command.
     * @throws Exception
     */
    public function handle(Dev $dev): int
    {
        $source = $this->option('source');
        if ($source && ! in_array($source, self::KNOWN_SOURCES)) {
            $this->line("Unknown source $source, please use one of: " . implode(', ', array_keys(self::KNOWN_SOURCES)));

            return 1;
        }

        return $dev->runner->execute([new CdStep(self::KNOWN_SOURCES[$source] ?? 'github.com', $this->argument('repo'))]);
    }
}
