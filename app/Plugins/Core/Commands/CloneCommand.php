<?php

namespace App\Plugins\Core\Commands;

use App\Config\Project\Definition;
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

        if (empty($fullName)) {
            $this->components->error('Repository full name cannot be empty');

            return 1;
        }

        $definition = new Definition($fullName, self::KNOWN_SOURCES[$source] ?? 'github.com');

        return $dev->runner->execute([
            new CloneStep($definition, $this->argument('args')),
            new CdStep($definition),
        ]);
    }
}
