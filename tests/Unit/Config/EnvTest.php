<?php

namespace Tests\Unit\Config;

use App\Config\Env;
use App\IO\IOInterface;
use App\Utils\Value;
use Illuminate\Support\Str;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

beforeEach(function (): void {
    Value::setIO(app(IOInterface::class));
});

it('resolves the environment variables', function (): void {
    $env = new Env(collect([
        'APP_ENV'   => 'production',
        'APP_DEBUG' => 'false',
        'APP_URL'   => 'https://example.com',
    ]));

    $resolved = $env->resolve();

    expect($resolved[0]->toArray())->toBe([
        'APP_ENV'   => 'production',
        'APP_DEBUG' => 'false',
        'APP_URL'   => 'https://example.com',
    ]);
    expect($resolved[1])->toBe([]);
    expect($env->wasPrompted())->toBeFalse();
});

it('resolves the environment variables with substitutions', function (): void {
    $env = new Env(collect([
        'APP_ENV'       => 'production',
        'APP_DEBUG'     => 'false',
    ]));

    $resolved = $env->resolve();
    expect($resolved[0]->toArray())->toBe([
        'APP_ENV'       => 'production',
        'APP_DEBUG'     => 'false',
    ]);
    expect($resolved[1])->toBe([]);
    expect($env->wasPrompted())->toBeFalse();
});

it('resolves the environment variables with prompted values', function (): void {
    Prompt::fake([
        $input = Str::random(),
        Key::ENTER,
    ]);

    $env = new Env(collect([
        'APP_ENV'   => 'production',
        'APP_DEBUG' => 'false',
        'APP_URL'   => 'https://example.com',
        'PROMPT'    => '$PROMPT(text: Please enter your APP_ENV)',
    ]));

    $resolved = $env->resolve();
    expect($resolved[0]->toArray())->toBe([
        'APP_ENV'   => 'production',
        'APP_DEBUG' => 'false',
        'APP_URL'   => 'https://example.com',
        'PROMPT'    => $input,
    ]);
    expect($resolved[1])->toBe(['PROMPT' => $input]);
    expect($env->wasPrompted())->toBeTrue();
});

it('resolves the environment variables with substitutions and then prompted values', function (): void {
    Prompt::fake([
        $input = Str::random(),
        Key::ENTER,
    ]);

    $env = new Env(collect([
        'APP_ENV'       => 'production',
        'APP_DEBUG'     => 'false',
        'PROMPT_VALUE'  => '${PROMPT}',
    ]), [
        'PROMPT'        => '$PROMPT(text: Please enter your APP_ENV)',
    ]);

    $resolved = $env->resolve();
    expect($resolved[0]->toArray())->toBe([
        'APP_ENV'             => 'production',
        'APP_DEBUG'           => 'false',
        'PROMPT_VALUE'        => $input,
    ]);
    expect($resolved[1])->toBe(['PROMPT_VALUE' => $input]);
    expect($env->wasPrompted())->toBeTrue();
});

it('uses prompted values when available instead of prompting', function (): void {
    Prompt::fake();

    $env = new Env(collect([
        'PROMPT'        => '$PROMPT(text: Please enter your APP_ENV)',
    ]));

    $input = Str::random();
    $resolved = $env->resolve(['PROMPT' => $input]);
    expect($resolved[0]->toArray())->toBe([
        'PROMPT'        => $input,
    ]);

    expect($resolved[1])->toBe(['PROMPT' => $input]);
    expect($env->wasPrompted())->toBeFalse();
    expect(Prompt::content())->toBeEmpty();
});
