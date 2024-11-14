<?php

namespace App\Plugins\Core\Commands;

use App\Config\Config;
use App\Config\Project;
use App\Config\Project\Definition;
use App\Dev;
use App\Exceptions\UserException;
use App\Execution\Runner;
use App\Factory;
use App\Plugin\Contracts\Step;
use App\Plugin\Contracts\Step\Deferred;
use App\Plugins\Core\Steps\CacheFilesStep;
use App\Plugins\Core\Steps\CheckUpdateStep;
use App\Plugins\Core\Steps\CloneStep;
use App\Repository\Repository;
use Exception;
use LaravelZero\Framework\Commands\Command;

class UpCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'up  {--self : Skip dependency projects}
                                {--force : Force the execution of steps}';

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
        if (! $dev->initialized()) {
            throw new UserException('DEV is not initialized for this project. Run `dev init` to initialize DEV.');
        }

        if (! $this->option('self') && $dev->config->projects()->count() > 0) {
            $this->info("🚀 Project contains {$dev->config->projects()->count()} dependency projects. Resolving all dependency projects...");
            $this->config->projects()->each(fn (Definition $project) => $this->resolveProject($project, $this->config->path()));
        }

        $this->repository->addProject($rootProject = new Project($dev));

        $projects = $this->repository->getProjects();
        $force = $this->option('force');

        /** @var array{Project, Step}[] */
        $deferred = [];
        foreach ($projects as $project) {
            $steps = $project->steps();
            if ($steps->count() === 0) {
                continue;
            }

            $this->info("🚀 Running steps for $project->id...");
            foreach ($steps as $step) {
                if ($step instanceof Deferred) {
                    $deferred[] = [$project, $step];
                    continue;
                }

                if (! $project->dev->runner->execute($step, $force)) {
                    throw new UserException(
                        ($name = $step->name()) ? "Failed to run step '$name' in $project->id" : "Failed to run step in $project->id"
                    );
                }
            }
        }

        $deferred[] = [$rootProject, new CacheFilesStep($dev)];
        $deferred[] = [$rootProject, new CheckUpdateStep()];
        $result = $this->runDeferred($deferred, $force);
        $dev->config->writeSettings();

        return $result;
    }

    /**
     * @param array{Project, Step}[] $deferred
     * @throws Exception
     */
    protected function runDeferred(array $deferred, bool $force): int
    {
        foreach ($deferred as [$project, $step]) {
            if (! $project->dev->runner->execute($step, $force)) {
                throw new UserException(
                    ($name = $step->name())
                        ? "Failed to run deferred step '$name' in $project->id"
                        : "Failed to run deferred step in $project->id"
                );
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param Definition $projectDefinition
     * @throws UserException
     * @throws Exception
     */
    private function resolveProject(Definition $projectDefinition, string $root): Project
    {
        /**
         * First we check if the project is already in the repository.
         * If, so, this means its already been cloned, and we can just return it.
         * This will also eventually prevent infinite loops caused by circular dependencies.
         */
        if ($project = $this->repository->getProject($projectDefinition)) {
            return $project;
        }

        /**
         * If the project is not in the repository, we need to clone it.
         * We also need to resolve any dependencies it has.
         * ToDo: Handle error if the project does not exist or not clonable
         */
        if (! $this->runner->execute([new CloneStep($projectDefinition, ['--depth=1'], $root, true)])) {
            throw new UserException("Failed to clone $projectDefinition");
        }

        $config = Config::fromProjectName($projectDefinition, $root);
        if ($config->projects()->isNotEmpty()) {
            $config->projects()->each(fn (Definition $project) => $this->resolveProject($project, $root));
        }

        $dev = Factory::create($this->runner->io(), $config);
        $project = new Project($dev);

        /**
         * We don't want to add the project to the queue if it's not using DEV.
         */
        if ($dev->initialized()) {
            $this->repository->addProject($project);
        }

        return $project;
    }
}
