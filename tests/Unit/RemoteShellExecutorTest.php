<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Shell\RemoteShellExecutor;
use Illuminate\Support\Facades\Config;
use phpseclib3\Net\SSH2;
use Tests\TestCase;

class RemoteShellExecutorTest extends TestCase
{
    protected Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = new Server([
            'hostname'  => '127.0.0.1',
            'port'      => 22,
            'username'  => 'root',
            'auth_type' => 'password',
            'ssh_password' => 'secret',
        ]);

        Config::set('larapanel.security.allowed_sudo_commands', [
            'ls', 'systemctl', 'docker'
        ]);
    }

    public function test_with_env_clones_executor_with_environment_variables()
    {
        $executor = new RemoteShellExecutor($this->server);
        $executorWithEnv = $executor->withEnv(['FOO' => 'bar', 'BAZ' => 'qux']);

        $this->assertNotSame($executor, $executorWithEnv);
        
        $refProp = new \ReflectionProperty(RemoteShellExecutor::class, 'envVars');
        $this->assertEquals([], $refProp->getValue($executor));
        $this->assertEquals(['FOO' => 'bar', 'BAZ' => 'qux'], $refProp->getValue($executorWithEnv));
    }

    public function test_validate_command_blocks_unauthorized_commands()
    {
        $executor = new RemoteShellExecutor($this->server);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unauthorized command: [unauthorized_bin]');

        $executor->run(['unauthorized_bin', '-la']);
    }

    public function test_run_executes_ssh_and_captures_stdout_stderr_and_exit_code()
    {
        $sshMock = $this->createMock(SSH2::class);
        $sshMock->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('FOO='))
            ->willReturn("file1.txt\nfile2.txt");
            
        $sshMock->method('getStdError')->willReturn("");
        $sshMock->method('getExitStatus')->willReturn(0);

        $executor = new class($this->server, $sshMock) extends RemoteShellExecutor {
            public function __construct(Server $server, protected SSH2 $mockConn)
            {
                parent::__construct($server);
            }

            protected function getConnection(): SSH2
            {
                return $this->mockConn;
            }
        };

        $result = $executor->withEnv(['FOO' => 'bar'])->run(['ls', '-la']);

        $this->assertTrue($result->successful());
        $this->assertEquals(0, $result->exitCode);
        $this->assertStringContainsString('file1.txt', $result->stdout);
    }
}
