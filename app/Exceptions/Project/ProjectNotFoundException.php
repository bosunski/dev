<?php

namespace App\Exceptions\Project;

use App\Contracts\Exception\Printable;
use App\Contracts\Solution\BaseSolution;
use App\Contracts\Solution\ProvidesSolution;
use App\Contracts\Solution\Solution;
use App\Exceptions\UserException;

class ProjectNotFoundException extends UserException implements Printable, ProvidesSolution
{
    public function __construct(protected string $project)
    {
        parent::__construct("Dependency project $project not found in this project config or not enabled");
    }

    public function print(): string
    {
        return "Dependency project \e[33m$this->project\e[0m not found in this project config or not enabled";
    }

    public function solution(): Solution
    {
        return new BaseSolution(
            "Project $this->project is missing",
            "Register the project in dev.yml file or enable it if it is disabled using `dev project:enable $this->project`"
        );
    }
}
