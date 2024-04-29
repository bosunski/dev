<?php

namespace App\Plugins\Core\Commands;

use App\Config\Config;
use App\Config\Project;
use App\Dev;
use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Factory;
use App\Plugins\Core\Steps\CacheFilesStep;
use App\Plugins\Core\Steps\CloneStep;
use App\Repository\Repository;
use Exception;
use LaravelZero\Framework\Commands\Command;

class UpCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'up {--self : Skip dependency projects}';

    /**
     * @var string
     */
    protected $description = 'Boostrap a project by running all configured steps';

    protected readonly Config $config;

    protected readonly Runner $runner;

    /**
     * @throws UserException
     */
    public function __construct(protected readonly Repository $repository, Dev $dev)
    {
        parent::__construct();

        $this->config = $dev->config;
        $this->runner = $dev->runner;
    }

    /**
     * @throws Exception
     */
    public function handle(Dev $dev): int
    {
        if (! $this->option('self') && $dev->config->projects()->count() > 0) {
            $this->info("🚀 Project contains {$dev->config->projects()->count()} dependency projects. Resolving all dependency projects...");
            $this->config->projects()->each(fn (string $project) => $this->resolveProject($project, $this->config->path()));
        }

        $this->repository->addProject($rootProject = new Project($dev));

        $projects = $this->repository->getProjects();
        foreach ($projects as $project) {
            $steps = $project->steps();
            if ($steps->count() === 0) {
                continue;
            }

            $this->info("🚀 Running steps for $project->id...");
            if ($project->dev->runner->execute($steps->all()) !== 0) {
                $this->error("⛔️ Failed to run steps for $project->id");

                return self::FAILURE;
            }
        }

        $result = $rootProject->dev->runner->execute(new CacheFilesStep($dev));
        $dev->config->writeSettings();

        return $result;
    }

    /**
     * @param non-empty-string $projectName
     * @throws UserException
     * @throws Exception
     */
    private function resolveProject(string $projectName, string $root): Project
    {
        /**
         * First we check if the project is already in the repository.
         * If, so, this means its already been cloned, and we can just return it.
         * This will also eventually prevent infinite loops caused by circular dependencies.
         */
        if ($project = $this->repository->getProject($projectName)) {
            return $project;
        }

        /**
         * If the project is not in the repository, we need to clone it.
         * We also need to resolve any dependencies it has.
         * ToDo: Handle error if the project does not exist or not clonable
         */
        if ($this->runner->execute([new CloneStep($projectName, 'github.com', ['--depth=1'], $root, true)]) !== 0) {
            throw new UserException("Failed to clone $projectName");
        }

        $config = Config::fromProjectName($projectName, $root);
        if ($config->projects()->isNotEmpty()) {
            $config->projects()->each(fn (string $project) => $this->resolveProject($project, $root));
        }

        $dev = Factory::create($this->runner->io(), $config);
        $this->repository->addProject($project = new Project($dev));

        return $project;
    }
}
