<?php

namespace App\Plugin\Contracts;

interface Config
{
    /**
     * @return array<int, Step>
     */
    public function steps(): array;
}
