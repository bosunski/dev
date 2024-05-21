<?php

namespace App\Commands;

use App\Config\Config;
use App\Dev;
use LaravelZero\Framework\Commands\Command;

class HookCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'hook';

    /**
     * @var string
     */
    protected $description = 'Hook';

    public function handle(Dev $dev): int
    {
        if (! $dev->initialized()) {
            return 0;
        }

        $prefix = ' DEV ';
        $prefix = "\e[30;107m$prefix\e[0m"; // Black text on white background

        if ($message = $this->trackedFilesHaveChanged($dev->config)) {
            $message = "\e[90m" . $message . "\e[0m";
            $dev->io()->write(PHP_EOL . $prefix . ' ' . $message . PHP_EOL);
        }

        return 0;
    }

    protected function trackedFilesHaveChanged(Config $config): string|false
    {
        $locks = $config->settings['locks'] ?? [];
        foreach($locks as $name => $md5) {
            if (@md5_file($config->cwd($name)) !== $md5) {
                $name = "\e[33m$name\e[90m";

                return "The file $name has changed, you should run dev up!";
            }
        }

        return false;
    }
}
