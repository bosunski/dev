<?php

namespace App\Step\Composer;

use App\Config\Composer\Auth;
use App\Execution\Runner;
use App\Step\StepInterface;
use Exception;
use function Laravel\Prompts\password;

class AuthStep implements StepInterface
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function name(): string
    {
        return "Configure auth for {$this->auth->host}";
    }

    /**
     * @throws Exception
     */
    public function command(): ?string
    {
        return match ($this->auth->type) {
            'basic' => "composer global config http-basic.{$this->auth->host} {$this->auth->username} {$this->auth->password}",
            default => throw new Exception("Unknown auth type: {$this->auth->type}"),
        };
    }

    /**
     * @throws Exception
     */
    public function checkCommand(): ?string
    {
        return $this->command();
    }

    /**
     * @throws Exception
     */
    public function run(Runner $runner): bool
    {
        $this->ensureTokenOrPassword($runner);

        return $runner->exec($this->command());
    }

    private function ensureTokenOrPassword(Runner $runner): void
    {
        if ($this->auth->isBasic() && ! $this->auth->hasPassword()) {
            $runner->io()->getOutput()->writeln("");
            $this->auth->password = password("Enter password for {$this->auth->host}:");
        }
    }

    /**
     * @throws Exception
     */
    public function done(Runner $runner): bool
    {
        $output = json_decode(`composer global config {$this->auth->getConfigName()}`, true);
        return $this->auth->validate($output ?? []);
    }

    public function id(): string
    {
        return "composer.auth.{$this->auth->host}";
    }
}
