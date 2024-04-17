<?php

namespace App\Commands;

use App\Dev;
use App\Exceptions\UserException;
use App\Utils\Browser;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\select;

class OpenCommand extends Command
{
    private const DEFAULT_SITE = '_default_';

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'open {site? : The name of the site to open}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Open a site in the browser';

    /**
     * @throws UserException
     */
    public function __construct(protected Dev $dev)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     *
     * @throws UserException
     */
    public function handle(): int
    {
        $site = $this->argument('site');
        $sites = $this->dev->config->sites();

        if ($sites->isEmpty()) {
            throw new UserException('No sites found');
        }

        if ($site && ! $sites->has($site)) {
            $this->error("Site $site not found. Are you sure you have it configured?");

            return self::FAILURE;
        }

        if (! $site) {
            $site = select(
                label: 'Which site will you like to open?',
                // @phpstan-ignore-next-line
                options: $sites->map($this->formatSite(...)),
            );

            assert(is_string($site), 'Selected site must be a string');
        }

        $url = $sites->get($site);
        if (! $url) {
            $this->error('No site selected');

            return self::FAILURE;
        }

        $this->info("Opening $url");
        Browser::open($url);

        return self::SUCCESS;
    }

    private function formatSite(string $url, string $site): string
    {
        $site = $site === self::DEFAULT_SITE ? 'Default' : $site;
        $site = Str::title($site);

        return "$site ($url)";
    }
}
