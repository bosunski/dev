<?php

namespace App\Plugins\Core\Commands;

use App\Dev;
use App\Plugins\Core\Steps\CdStep;
use App\Plugins\Core\Steps\CloneStep;
use Exception;
use InvalidArgumentException;
use LaravelZero\Framework\Commands\Command;

class CloneCommand extends Command
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
    protected $signature = 'clone {repo} {args?*} {--source=}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Clones a GitHub repository';

    /**
     * Execute the console command.
     * @return int
     * @throws Exception
     */
    public function handle(Dev $dev): int
    {
        $source = $this->option('source');
        if ($source && ! in_array($source, self::KNOWN_SOURCES)) {
            $this->line("Unknown source $source, please use one of: " . implode(', ', array_keys(self::KNOWN_SOURCES)));

            return 1;
        }

        assert(is_string($fullName = $this->argument('repo')), new InvalidArgumentException('Repository full name must be a string'));

        return $dev->runner->execute([
            new CloneStep($fullName, $source = self::KNOWN_SOURCES[$source] ?? 'github.com', $this->argument('args')),
            new CdStep($this->argument('repo'), $source),
        ]);
    }
}
