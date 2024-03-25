<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface EnvResolver
{
    /**
     * @return Collection<string, string>
     */
    public function envs(): Collection;
}
