<?php

namespace App\Step;

use App\Config\Config;
use App\Exceptions\UserException;
use App\Execution\Runner;
use Exception;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class UpStep implements StepInterface
{
    public const FILE_NAME = 'garm.yaml';

    public function __construct(private readonly string $path)
    {
    }

    public function id(): string
    {
        return "up-$this->path";
    }

    public function name(): string
    {
        return "Setting up $this->path";
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
        $config = new Config($this->path, $this->parseYaml());
        $runner = new Runner($config, $runner->io());

        $cwd = getcwd();
        chdir($config->cwd());
        $result = $runner->execute($config->up()->steps()) === 0;

        if ($cwd !== getcwd()) {
            chdir($cwd);
        }

        return $result;
    }

    /**
     * @throws UserException
     */
    private function parseYaml(): array
    {
        if (! file_exists($this->fullPath())) {
            return [];
        }

        try {
            return Yaml::parseFile($this->fullPath());
        } catch (ParseException $e) {
            throw new UserException($e->getMessage());
        }
    }

    private function fullPath(): string
    {
        return $this->path . '/' . self::FILE_NAME;
    }

    public function done(Runner $runner): bool
    {
        return false;
    }
}
