<?php

namespace App\Plugins\Composer\Steps;

use App\Execution\Runner;
use App\Plugin\Contracts\Step;
use App\Plugins\Composer\Config\Auth;
use App\Utils\Value;
use Exception;

/**
 * @phpstan-import-type RawAuth from Auth
 */
class AuthStep implements Step
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
    public function run(Runner $runner): bool
    {
        $command = match ($this->auth->type) {
            'basic' => "composer global config http-basic.{$this->auth->host} {$this->auth->username} {$this->resolvePassword()}",
            default => throw new Exception("Unknown auth type: {$this->auth->type}"),
        };

        return $runner->exec($command);
    }

    protected function resolvePassword(): string
    {
        return Value::from($this->auth->password)->resolve();
    }

    /**
     * @throws Exception
     */
    public function done(Runner $runner): bool
    {
        $result = $runner->process("composer global config {$this->auth->getConfigName()}")->run();
        if ($result->failed()) {
            return false;
        }

        $output = json_decode($result->output(), true);
        if (! is_array($output)) {
            return false;
        }

        /**
         * ToDo: Maybe validate the decoded JSON?
         */
        // @phpstan-ignore-next-line
        return $this->validate($output);
    }

    /**
     * @param RawAuth $data
     * @return bool
     */
    public function validate(array $data): bool
    {
        return match ($this->auth->type) {
            'basic' => $this->validateBasic($data),
            default => false,
        };
    }

    /**
     * @param RawAuth $data
     * @return bool
     */
    private function validateBasic(array $data): bool
    {
        if (! isset($data['username'], $data['password'])) {
            return false;
        }

        return $this->auth->username === $data['username'];
    }

    public function id(): string
    {
        return "composer.auth.{$this->auth->host}";
    }
}
