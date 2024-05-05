<?php

namespace Tests\Unit\Config;

use App\Config\Config;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
});

test('getName returns name', function (): void {
    $config = new Config('foo/bar', ['name' => 'test']);
    expect($config->getName())->toBe('test');
});

test('commands returns commands', function (): void {
    $config = new Config('foo/bar', ['commands' => ['test']]);
    expect($config->commands()->all())->toBe(['test']);
});

test('steps returns steps', function (): void {
    $config = new Config('foo/bar', ['steps' => ['test']]);
    expect($config->steps())->toBe(['test']);
});
