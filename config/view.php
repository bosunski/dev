<?php

use function Illuminate\Filesystem\join_paths;

return [
    'paths' => [
        resource_path('views'),
    ],
    'compiled' => Phar::running()
        ? join_paths(sys_get_temp_dir(), 'dev/views')
        : env('VIEW_COMPILED_PATH', realpath(storage_path('framework/views'))),
];
