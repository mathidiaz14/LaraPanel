<?php

namespace Tests\Unit;

use App\Contracts\ShellExecutorContract;
use App\Shell\FakeShellExecutor;
use App\Shell\ShellExecutor;
use App\Shell\ShellResult;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShellExecutorContractTest extends TestCase
{
    private FakeShellExecutor $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeShellExecutor();
    }

    #[Test]
    public function fake_implements_the_contract()
    {
        $this->assertInstanceOf(ShellExecutorContract::class, $this->fake);
        $this->assertInstanceOf(ShellExecutorContract::class, new ShellExecutor());
    }

    #[Test]
    public function run_records_commands_and_returns_configured_result()
    {
        $this->fake->setResult(new ShellResult(0, 'hello', '', 'echo hello'));

        $result = $this->fake->run(['echo', 'hello']);

        $this->assertTrue($this->fake->ran(['echo', 'hello']));
        $this->assertSame(['echo', 'hello'], $this->fake->lastCommand());
        $this->assertTrue($result->successful());
        $this->assertSame('hello', $result->output());
    }

    #[Test]
    public function run_throws_when_configured_result_fails_and_check_exit_is_true()
    {
        $this->fake->setResult(new ShellResult(1, '', 'boom', 'false'));

        $this->expectException(\RuntimeException::class);
        $this->fake->run(['false']);
    }

    #[Test]
    public function fluent_setters_return_clones_without_mutating_the_original()
    {
        $cloned = $this->fake
            ->withTimeout(10)
            ->inDirectory('/tmp')
            ->withEnv(['FOO' => 'bar'])
            ->withInput('payload');

        $this->assertNotSame($this->fake, $cloned);
        $this->assertCount(1, $cloned->configurations);
        $this->assertSame(60, $this->fake->getTimeout());
        $this->assertSame(10, $cloned->getTimeout());
        $this->assertSame('/tmp', $cloned->getWorkingDirectory());
        $this->assertSame(['FOO' => 'bar'], $cloned->getEnvVars());
        $this->assertSame('payload', $cloned->getInput());
    }
}
