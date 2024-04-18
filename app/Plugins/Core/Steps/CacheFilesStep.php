<?php

namespace App\Plugins\Core\Steps;

use App\Config\Config;
use App\Dev;
use App\Execution\Runner;
use App\Plugin\Contracts\Step;

/**
 * @phpstan-import-type Script from Config
 */
class CacheFilesStep implements Step
{
    public function __construct(protected readonly Dev $dev)
    {
    }

    public function name(): string
    {
        return 'Lock Files';
    }

    public function run(Runner $runner): bool
    {
        $locks = [];
        foreach (Config::LockFiles as $file) {
            $path = $this->dev->config->cwd($file);
            if (file_exists($path)) {
                $locks[$file] = md5_file($path);
            }
        }

        $this->dev->config->settings['locks'] = $locks;
        $this->dev->config->writeSettings();

        return true;
    }

    public function done(Runner $runner): bool
    {
        return false;
    }

    public function id(): string
    {
        return 'cache-files';
    }
}
