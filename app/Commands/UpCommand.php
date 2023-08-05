<?php

namespace App\Commands;

use App\Config\Config;
use App\Execution\Runner;
use Exception;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Yaml\Yaml;

class UpCommand extends Command
{
    private const FILE_NAME = "garm.yaml";

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'up';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Boostrap a project';

    /**
     * @throws Exception
     */
    public function handle(): int
    {
        $config = new Config(Yaml::parseFile(self::FILE_NAME));
        $runner = new Runner($config, $this);

        return $runner->execute();
    }
}
