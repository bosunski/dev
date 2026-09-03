<?php

echo implode(':', [
    getenv('SMOKE_CONFIGURED'),
    getenv('SMOKE_APOSTROPHE'),
    $_SERVER['REQUEST_URI'],
    $_SERVER['HTTP_X_SMOKE'] ?? 'missing-header',
]);
