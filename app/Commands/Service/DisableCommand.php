<?php

namespace App\Commands\Service;

use App\Dev;
use App\Exceptions\UserException;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\select;

class DisableCommand extends Command
{
    protected $signature = 'project:disable {project?}';

    protected $description = 'Disable a registered and disabled dependency project';

    /**
     * @throws UserException
     */
    public function __construct(protected Dev $dev)
    {
        parent::__construct();
    }

    /**
     * @throws UserException
     */
    public function handle(): int
    {
        $projects = $this->dev->config->projects(true);
        if ($projects->isEmpty()) {
            $this->error('No registered projects found');

            return self::INVALID;
        }

        if (! $project = $this->argument('project')) {
            $project = $this->askForProject($projects->all());
        }

        if (! $projects->contains($project)) {
            $this->error("Service $project not found in configuration");

            return self::INVALID;
        }

        $disabledProjects = $this->dev->config->settings['disabled'] ?? [];
        if (in_array($project, $disabledProjects)) {
            $this->info("Service $project is already disabled");

            return self::SUCCESS;
        }

        $this->dev->config->settings['disabled'][] = $project;
        $this->dev->config->writeSettings();

        $this->info("Service $project disabled");

        return self::SUCCESS;
    }

    /**
     * @param string[] $projects
     * @throws UserException
     */
    private function askForProject(array $projects): string
    {
        $project = select('Which project do you want to disable?', $projects);
        if (! $project) {
            throw new UserException('No project selected');
        }

        assert(is_string($project), 'Project must be a string');

        return $project;
    }
}
