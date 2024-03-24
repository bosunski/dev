<?php

namespace App\Plugin\Capability;

enum Capabilities: string
{
    case Command = CommandProvider::class;
    case Config = ConfigProvider::class;
    // case Path = PathProvider::class;
}
