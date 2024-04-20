<?php

namespace Tests\Unit\Utils;

use App\IO\StdIO;
use App\Utils\Value;
use Laravel\Prompts\Output\ConsoleOutput;
use Symfony\Component\Console\Input\ArrayInput;
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
        $this->assertEquals('Hello World', $value->substitute($substitutions));
    }

    /**
     * @test
     * @return void
     */
    public function testEvaluate(): void
    {
        $value = new Value('`echo "Hello World"`');
        $this->assertEquals('Hello World', $value->evaluate());
    }

    /**
     * @test
     */
    public function evaluateWithFailedProcess(): void
    {
        $this->expectExceptionMessage('Failed to evaluate environment variable: `echo "Hello World" && exit 1`. Output: Hello World');
        $value = new Value('`echo "Hello World" && exit 1`');
        $value->evaluate();
    }

    /**
     * @test
     */
    public function evaluateWithEmptyValue(): void
    {
        $value = new Value('');
        $this->assertEquals('', $value->evaluate());
    }

    /**
     * @test
     */
    public function substituteWithEmptyValue(): void
    {
        $value = new Value('');
        $substitutions = collect(['name' => 'World']);
        $this->assertEquals('', $value->substitute($substitutions));
    }

    /**
     * @test
     */
    public function substituteWithEmptySubstitutions(): void
    {
        $value = new Value('Hello ${name}');
        $substitutions = collect([]);
        $this->assertEquals('Hello ${name}', $value->substitute($substitutions));
    }

    /**
     * @test
     */
    public function substituteWithEmptySubstitutionsAndEmptyValue(): void
    {
        $value = new Value('');
        $substitutions = collect([]);
        $this->assertEquals('', $value->substitute($substitutions));
    }

    /**
     * @test
     */
    public function parsePrompts(): void
    {
        $io = new StdIO(new ArrayInput([]), new ConsoleOutput());
        Value::setOutput($io);

        $value = new Value('$PROMPT(password: Please enter your BAR key)');
        $substitutions = collect(['name' => 'World']);
        $this->assertEquals('Hello World', $value->parsePrompts());
    }
}
