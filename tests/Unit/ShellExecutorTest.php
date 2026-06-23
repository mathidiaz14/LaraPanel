<?php

namespace Tests\Unit;

use App\Shell\ShellExecutor;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class ShellExecutorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Force production environment to trigger whitelist validation
        $this->app['env'] = 'production';
        
        Config::set('larapanel.security.allowed_sudo_commands', [
            'nginx', 'systemctl', 'docker', 'ls'
        ]);
    }

    public function test_it_allows_whitelisted_commands()
    {
        $executor = new class extends ShellExecutor {
            public function testValidate(array $command) {
                $this->validateCommand($command);
            }
        };

        // Should not throw exception
        $executor->testValidate(['nginx', '-t']);
        $executor->testValidate(['systemctl', 'restart', 'mysql']);
        $executor->testValidate(['sudo', '-n', 'docker', 'ps']);
        $executor->testValidate(['sudo', '-u', 'www-data', 'ls', '-la']);
        
        $this->assertTrue(true);
    }

    public function test_it_blocks_unauthorized_commands()
    {
        $executor = new class extends ShellExecutor {
            public function testValidate(array $command) {
                $this->validateCommand($command);
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unauthorized command: [cat]');

        $executor->testValidate(['cat', '/etc/shadow']);
    }

    public function test_it_blocks_unauthorized_commands_with_sudo()
    {
        $executor = new class extends ShellExecutor {
            public function testValidate(array $command) {
                $this->validateCommand($command);
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unauthorized command: [rm]');

        $executor->testValidate(['sudo', '-n', 'rm', '-rf', '/']);
    }
}
