<?php

namespace Tests\Unit\Utils;

use App\IO\IOInterface;
use App\Utils\Value;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Tests\TestCase;

class ValueTest extends TestCase
{
    /**
     * @test
     */
    public function substitute(): void
    {
        $value = new Value('Hello ${name}');
        $substitutions = collect(['name' => 'World']);
        $this->assertEquals('Hello World', $value->resolve($substitutions));
    }

    /**
     * @test
     * @return void
     */
    public function testEvaluate(): void
    {
        $value = new Value('`echo "Hello World"`');
        $this->assertEquals('Hello World', $value->resolve());
    }

    /**
     * @test
     */
    public function evaluateWithFailedProcess(): void
    {
        $this->expectExceptionMessage('Failed to evaluate environment variable: `echo "Hello World" && exit 1`. Output: Hello World');
        $value = new Value('`echo "Hello World" && exit 1`');
        $value->resolve();
    }

    /**
     * @test
     */
    public function evaluateWithEmptyValue(): void
    {
        $value = new Value('');
        $this->assertEquals('', $value->resolve());
    }

    /**
     * @test
     */
    public function substituteWithEmptyValue(): void
    {
        $value = new Value('');
        $substitutions = collect(['name' => 'World']);
        $this->assertEquals('', $value->resolve($substitutions));
    }

    /**
     * @test
     */
    public function substituteWithEmptySubstitutions(): void
    {
        $value = new Value('Hello ${name}');
        $substitutions = collect([]);
        $this->assertEquals('Hello ${name}', $value->resolve($substitutions));
    }

    /**
     * @test
     */
    public function substituteWithEmptySubstitutionsAndEmptyValue(): void
    {
        $value = new Value('');
        $substitutions = collect([]);
        $this->assertEquals('', $value->resolve($substitutions));
    }

    /**
     * @test
     */
    public function parseATextPrompts(): void
    {
        Prompt::fake([
            'Hello World',
            Key::ENTER,
        ]);

        $io = app(IOInterface::class);
        Value::setIO($io);

        $value = new Value('$PROMPT(text: Please enter your BAR key)');
        $this->assertEquals('Hello World', $value->resolve());
    }

    public function parseAPasswordPrompts(): void
    {
        Prompt::fake([
            'Hello World',
            Key::ENTER,
        ]);

        $io = app(IOInterface::class);
        Value::setIO($io);

        $value = new Value('$PROMPT(password: Please enter your BAR key)');
        $this->assertEquals('Hello World', $value->resolve());
    }

    public function parseAPasswordPromptsWithDefault(): void
    {
        Prompt::fake([
            'Hello World',
            Key::ENTER,
        ]);

        $io = app(IOInterface::class);
        Value::setIO($io);

        $value = new Value('$PROMPT(password: Please enter your BAR key, default: Hello World)');
        $this->assertEquals('Hello World', $value->resolve());
    }

    /**
     * @test
     */
    public function promptWithArrayValue(): void
    {
        Prompt::fake([
            'Hello World',
            Key::ENTER,
        ]);

        $io = app(IOInterface::class);
        Value::setIO($io);

        $value = new Value([
            'prompt' => 'Please enter your BAR key',
            'type'   => 'password',
        ]);

        $this->assertEquals('Hello World', $value->resolve());
    }
}
