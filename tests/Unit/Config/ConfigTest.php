<?php

namespace Tests\Unit\Config;

use App\Config\Config;
use App\Config\Project\Definition;
use App\Config\UpConfig;
use Illuminate\Support\Facades\Storage;

use function Illuminate\Filesystem\join_paths;

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

test('projects returns projects', function (): void {
    $config = new Config('foo/bar', ['projects' => $projects = ['foo/project1', 'foo/project2']]);
    expect($config->projects()->count())->toBe(2);
    expect($config->projects()->pluck('repo')->all())->toBe($projects);
});

test('sites returns sites', function (): void {
    $config = new Config('foo/bar', ['sites' => ['site1' => 'url1', 'site2' => 'url2']]);
    expect($config->sites()->count())->toBe(2);
    expect($config->sites()->keys()->all())->toBe(['site1', 'site2']);
    expect($config->sites()->values()->all())->toBe(['url1', 'url2']);
});

test('up returns UpConfig instance', function (): void {
    $config = new Config('foo/bar', ['up' => ['step1', 'step2']]);
    expect($config->up())->toBeInstanceOf(UpConfig::class);
});

test('path returns correct path', function (): void {
    $config = new Config('/foo/bar', []);
    expect($config->path())->toBe('/foo/bar/.dev');
    expect($config->path('file.txt'))->toBe('/foo/bar/.dev/file.txt');
});

test('projectPath returns correct service path', function (): void {
    $config = new Config('/foo/bar', []);
    $path = $config->cwd(join_paths(Config::DevDir, Config::SrcDir, Config::DefaultSource));

    expect($config->projectPath())->toBe($path);
    expect($config->projectPath('file.txt'))->toBe(join_paths($path, 'file.txt'));
});

test('devPath returns correct dev path', function (): void {
    $config = new Config('/foo/bar', []);
    expect($config->devPath())->toBe('/foo/bar/.dev');
    expect($config->devPath('file.txt'))->toBe('/foo/bar/.dev/file.txt');
});

test('cwd returns correct current working directory', function (): void {
    $config = new Config('/foo/bar', []);
    expect($config->cwd())->toBe('/foo/bar');
    expect($config->cwd('file.txt'))->toBe('/foo/bar/file.txt');
});

test('globalPath returns correct global path', function (): void {
    $config = new Config('/foo/bar', []);
    $globalPath = join_paths($config->home(), Config::DevDir);
    expect($config->globalPath())->toBe($globalPath);
    expect($config->globalPath('file.txt'))->toBe(join_paths($globalPath, 'file.txt'));
});

test('home returns correct home directory', function (): void {
    expect(Config::home())->toBe(getenv('HOME'));
});

test('sourcePath returns correct source path', function (): void {
    $config = new Config('/foo/bar', []);
    $path = $config->home() . '/src/' . Config::DefaultSource;
    expect($config->sourcePath())->toBe($path);
    expect($config->sourcePath('file.txt'))->toBe("$path/file.txt");
});

test('projectName returns correct project name', function (): void {
    $config = new Config('/foo/bar', []);
    expect($config->projectName())->toBe('foo/bar');
});

test('isDevProject returns true if config is not empty', function (): void {
    $config = new Config('/foo/bar', ['name' => 'test']);
    expect($config->isDevProject())->toBeTrue();
});

test('isDevProject returns false if config is empty', function (): void {
    $config = new Config('/foo/bar', []);
    expect($config->isDevProject())->toBeFalse();
});

test('read returns empty if file does not exists', function (): void {
    expect(Config::read('/path/to/nonexistent/file')->raw())->toBeEmpty();
});

test('fromPath returns Config instance', function (): void {
    $config = Config::fromPath('/foo/bar');
    expect($config)->toBeInstanceOf(Config::class);
});

test('fromProjectName returns Config instance', function (): void {
    $config = Config::fromProjectName('foo/bar');
    expect($config)->toBeInstanceOf(Config::class);
});

test('fromProjectDefinition returns Config instance', function (): void {
    $project = new Definition('foo/bar');
    $config = Config::fromProjectDefinition($project);
    expect($config)->toBeInstanceOf(Config::class);
});

test('getServe returns serve config', function (): void {
    $config = new Config('foo/bar', ['serve' => ['run' => 'serve']]);
    expect($config->getServe())->toBe(['run' => 'serve']);
});

test('envs returns resolved environment variables', function (): void {
    $config = new Config('foo/bar', ['env' => ['VAR1' => 'value1', 'VAR2' => 'value2']]);
    expect($config->envs()->count())->toBe(2);
    expect($config->envs()->keys()->all())->toBe(['VAR1', 'VAR2']);
    expect($config->envs()->values()->all())->toBe(['value1', 'value2']);
});

test('isDebug returns false', function (): void {
    $config = new Config('foo/bar', []);
    expect($config->isDebug())->toBeFalse();
});
