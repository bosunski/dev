<?php

use App\Config\Project\Definition;

it('parses project definition', function (string $project, string $fullName, string $cloneUrl, ?string $ref, string $source = 'github.com'): void {
    $definition = new Definition($project);

    expect($definition->ref)->toBe($ref);
    expect($definition->repo)->toBe($fullName);
    expect($definition->url)->toBe($cloneUrl);
    expect($definition->source)->toBe($source);
})->with([
    'foo/bar' => [
        'foo/bar',
        'foo/bar',
        'https://github.com/foo/bar.git',
        null,
    ],
    'foo/bar#baz' => [
        'foo/bar#baz',
        'foo/bar',
        'https://github.com/foo/bar.git',
        'baz',
    ],
    'http://example.com/foo/bar' => [
        'http://example.com/foo/bar',
        'foo/bar',
        'http://example.com/foo/bar.git',
        null,
        'example.com',
    ],
    'http://example.com/foo/bar#baz' => [
        'http://example.com/foo/bar#baz',
        'foo/bar',
        'http://example.com/foo/bar.git',
        'baz',
        'example.com',
    ],
    'http://example.com/foo/bar.git' => [
        'http://example.com/foo/bar.git',
        'foo/bar',
        'http://example.com/foo/bar.git',
        null,
        'example.com',
    ],
    'http://example.com/foo/bar.git#baz' => [
        'http://example.com/foo/bar.git#baz',
        'foo/bar',
        'http://example.com/foo/bar.git',
        'baz',
        'example.com',
    ],
    'http://example.com/foo/bar.git#draft/nice-hamilton' => [
        'http://example.com/foo/bar.git#draft/nice-hamilton',
        'foo/bar',
        'http://example.com/foo/bar.git',
        'draft/nice-hamilton',
        'example.com',
    ],
]);
