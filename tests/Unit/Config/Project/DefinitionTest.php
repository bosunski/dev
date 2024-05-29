<?php

use App\Config\Project\Definition;
use App\Exceptions\UserException;

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

it('throws an exception when project definition is empty', function (): void {
    expect(fn () => new Definition(''))->toThrow(new UserException('Cannot provide an empty project name'));
});

it('throws an exception when project definition is malformed', function (): void {
    expect(fn () => new Definition('http://'))->toThrow(new UserException('Malformed project repo URL http:// cannot be parsed'));
});

it('throws an exception when path is missing in project definition', function (): void {
    expect(fn () => new Definition('http://example.com'))->toThrow(new UserException('Malformed project repo URL http://example.com cannot be parsed'));
});

it('throws an exception when project definition has more than two parts', function (): void {
    expect(fn () => new Definition('http://example.com/foo/bar/baz'))->toThrow(new UserException('Malformed project repo URL http://example.com/foo/bar/baz cannot be parsed'));
});
