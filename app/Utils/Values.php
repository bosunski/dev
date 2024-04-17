<?php

namespace App\Utils;

use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class Values
{
    /**
     * @param string|null $value
     * @param Collection<string, string|null> $envs
     * @return string
     */
    public static function substituteEnv(string|null $value, Collection $envs): string|null
    {
        if (! $value) {
            return $value;
        }

        preg_match_all('/\${([^}]*)}/', $value, $matches);
        foreach ($matches[1] ?? [] as $match) {
            $replacement = $envs->get($match);
            if (! $replacement) {
                return $value;
            }

            $value = str_replace('${' . $match . '}', $replacement, $value);
        }

        return $value;
    }

    public static function evaluateEnv(string|null $value): string|null
    {
        if (! $value) {
            return $value;
        }

        preg_match_all('/`([^`]*)`/', $value, $matches);
        foreach ($matches[1] ?? [] as $match) {
            try {
                $output = Process::run($match)->throw()->output();
                $value = str_replace("`$match`", trim($output), $value);
            } catch (ProcessFailedException $e) {
                throw new InvalidArgumentException("Failed to evaluate environment variable: $value. Output: {$e->result->output()}");
            }
        }

        return $value;
    }
}
