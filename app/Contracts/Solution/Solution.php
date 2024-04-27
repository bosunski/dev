<?php

namespace App\Contracts\Solution;

interface Solution
{
    public function title(): string;

    public function description(): string;

    /**
     * @return array<string, string>
     */
    public function links(): array;
}
