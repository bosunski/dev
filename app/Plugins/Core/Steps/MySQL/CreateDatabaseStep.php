<?php

namespace App\Plugins\Core\Steps\MySQL;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use Illuminate\Process\Exceptions\ProcessFailedException;

class CreateDatabaseStep implements Step
{
    public const Host = 'mysql.dev.local';
    public const User = 'root';

    /**
     * @param string[]|string $databases
     * @return void
     */
    public function __construct(protected array | string $databases)
    {
    }

    public function id(): string
    {
        $databases = is_array($this->databases) ? implode('-', $this->databases) : $this->databases;

        return "mysql-create-database-$databases";
    }

    public function name(): ?string
    {
        return 'Create MySQL database';
    }

    public function run(Runner $runner): bool
    {
        $databases = is_array($this->databases) ? $this->databases : [$this->databases];
        $commands = collect($databases)->map(function ($database) {
            return "CREATE DATABASE IF NOT EXISTS $database;";
        })->toArray();

        $command = sprintf(
            'docker exec -i dev-mysql mysql -u%s -e "%s"',
            self::User,
            implode(' ', $commands)
        );

        try {
            return $runner->process($command)->run()->throw()->successful();
        } catch (ProcessFailedException $e) {
            echo $e->getMessage();

            return false;
        }
    }

    public function done(Runner $runner): bool
    {
        $databases = is_array($this->databases) ? $this->databases : [$this->databases];
        $command = sprintf(
            'docker exec dev-mysql mysql -u%s -e "SHOW DATABASES;"',
            self::User
        );

        $output = $runner->process($command)->run()->output();

        return collect($databases)->every(function ($database) use ($output) {
            return str_contains($output, $database);
        });
    }
}
