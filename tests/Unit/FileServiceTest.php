<?php

namespace Tests\Unit;

use App\Services\FileService;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FileServiceTest extends TestCase
{
    protected FileService $fileService;
    protected string $testRoot;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testRoot = sys_get_temp_dir() . '/larapanel_test_webroot_' . uniqid();
        @mkdir($this->testRoot, 0777, true);
        
        Config::set('larapanel.paths.webroots', $this->testRoot);
        
        // Force production to use config path instead of storage/app/public/webroot
        $this->app['env'] = 'production';
        
        $sudoMock = $this->createMock(SudoExecutor::class);
        $this->fileService = new FileService($sudoMock);
    }

    protected function tearDown(): void
    {
        @rmdir($this->testRoot);
        parent::tearDown();
    }

    public function test_it_resolves_valid_paths()
    {
        $path = $this->fileService->resolvePath('index.html');
        $this->assertEquals($this->testRoot . '/index.html', $path);
        
        $path = $this->fileService->resolvePath('/domain.com/public_html');
        $this->assertEquals($this->testRoot . '/domain.com/public_html', $path);
    }

    public function test_it_blocks_path_traversal_attempts()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Acceso no autorizado');

        // This attempts to escape the webroot
        $this->fileService->resolvePath('../../etc/passwd');
    }

    public function test_it_blocks_complex_path_traversal()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        // Even if it tries to trick by going inside then outside
        $this->fileService->resolvePath('domain.com/../../../../etc/shadow');
    }
}
