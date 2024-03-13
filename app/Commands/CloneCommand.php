<?php

namespace App\Commands;

use App\Config\Config;
use App\Execution\Runner;
use App\Step\CdStep;
use App\Step\Git\CloneStep;
use Exception;
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
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function handle(): int
    {
        $source = $this->option('source');
        if ($source && ! in_array($source, self::KNOWN_SOURCES)) {
            $this->line("Unknown source $source, please use one of: " . implode(', ', array_keys(self::KNOWN_SOURCES)));

            return 1;
        }

        $config = new Config(getcwd(), []);
        $runner = new Runner($config, $this);

        [$owner, $repo] = CloneStep::parseService($this->argument('repo'));

        return $runner->execute([
            new CloneStep($owner, $repo, $source = self::KNOWN_SOURCES[$source] ?? 'github.com', $this->argument('args')),
            new CdStep($this->argument('repo'), $source),
        ]);
    }
}
