<?php

namespace Tests\Unit;

use App\Services\FileService;
use App\Shell\SudoExecutor;
use App\Shell\ShellResult;
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

        // A SudoExecutor double that actually performs the filesystem effects
        // of the permitted sudo commands (cp/mv/mkdir/rm/chown/chmod), so the
        // service logic under test produces real on-disk results.
        $sudoMock = $this->createMock(SudoExecutor::class);
        $sudoMock->method('run')->willReturnCallback(function (array $command, bool $checkExit = true) {
            $this->executeSudoCommand($command, $checkExit);

            return new ShellResult(0, '', '', implode(' ', $command));
        });

        $this->fileService = new FileService($sudoMock);
    }

    protected function executeSudoCommand(array $command, bool $checkExit = true): void
    {
        $binary = $command[0] ?? '';
        $args   = array_slice($command, 1);

        switch ($binary) {
            case 'mkdir':
                $args = array_values(array_filter($args, fn ($a) => $a !== '-p'));
                $dest = end($args);
                if (is_string($dest)) {
                    @mkdir($dest, 0755, true);
                }
                break;

            case 'cp':
                $recursive = in_array('-r', $args, true);
                $args = array_values(array_filter($args, fn ($a) => $a !== '-r'));
                $src  = $args[0] ?? null;
                $dest = $args[1] ?? null;
                if ($src && $dest) {
                    if ($recursive || is_dir($src)) {
                        $this->copyTree($src, $dest);
                    } else {
                        @copy($src, $dest);
                    }
                }
                break;

            case 'mv':
                $src  = $args[0] ?? null;
                $dest = $args[1] ?? null;
                if ($src && $dest) {
                    if (!is_dir(dirname($dest))) {
                        @mkdir(dirname($dest), 0755, true);
                    }
                    @rename($src, $dest);
                }
                break;

            case 'rm':
                if (in_array('-f', $args, true)) {
                    $args = array_values(array_filter($args, fn ($a) => $a !== '-f'));
                }
                $target = end($args);
                if (is_string($target)) {
                    if (in_array('-r', $args, true) || in_array('-rf', $args, true) || is_dir($target)) {
                        $this->deleteTree($target);
                    } else {
                        @unlink($target);
                    }
                }
                break;

            case 'chown':
            case 'chmod':
                // Ownership/permissions are best-effort in tests.
                break;

            default:
                if ($checkExit) {
                    throw new \RuntimeException("Unexpected sudo command in test: " . implode(' ', $command));
                }
        }
    }

    protected function copyTree(string $src, string $dest): void
    {
        if (is_dir($src)) {
            @mkdir($dest, 0755, true);
            foreach (scandir($src) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $this->copyTree($src . '/' . $entry, $dest . '/' . $entry);
            }
        } elseif (is_file($src)) {
            @copy($src, $dest);
        }
    }

    protected function deleteTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $this->deleteTree($path . '/' . $entry);
            }
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }

    protected function tearDown(): void
    {
        @rmdir($this->testRoot);
        parent::tearDown();
    }

    public function test_it_resolves_valid_paths()
    {
        $normalizedRoot = str_replace('\\', '/', realpath($this->testRoot) ?: $this->testRoot);

        $path = $this->fileService->resolvePath('index.html');
        $this->assertEquals($normalizedRoot . '/index.html', $path);
        
        $path = $this->fileService->resolvePath('/domain.com/public_html');
        $this->assertEquals($normalizedRoot . '/domain.com/public_html', $path);
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

    public function test_it_blocks_sibling_directory_escape()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        // Prevents escaping to sibling directory with matching prefix (e.g. webroot2)
        $this->fileService->resolvePath('../' . basename($this->testRoot) . '2/secret.txt');
    }

    public function test_it_blocks_symlink_escape(): void
    {
        $outside = sys_get_temp_dir() . '/larapanel_outside_' . uniqid();
        @mkdir($outside, 0777, true);
        $link = $this->testRoot . '/outside-link';

        if (! @symlink($outside, $link)) {
            @rmdir($outside);
            $this->markTestSkipped('El sistema no permite crear enlaces simbólicos.');
        }

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->fileService->resolvePath('outside-link/secret.txt');
        } finally {
            @unlink($link);
            @rmdir($outside);
        }
    }

    public function test_it_does_not_allow_deleting_the_root(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->fileService->delete('');
    }

    public function test_it_copies_with_a_new_destination_name(): void
    {
        @mkdir($this->testRoot . '/sub', 0777, true);
        file_put_contents($this->testRoot . '/original.txt', 'content');

        $this->fileService->copy('original.txt', 'sub', 'renombrado (2).txt');

        $this->assertFileExists($this->testRoot . '/sub/renombrado (2).txt');
        $this->assertSame('content', file_get_contents($this->testRoot . '/sub/renombrado (2).txt'));
    }

    public function test_it_moves_with_a_new_destination_name(): void
    {
        @mkdir($this->testRoot . '/sub', 0777, true);
        file_put_contents($this->testRoot . '/original.txt', 'content');

        $this->fileService->move('original.txt', 'sub', 'renombrado (2).txt');

        $this->assertFileExists($this->testRoot . '/sub/renombrado (2).txt');
        $this->assertFalse(file_exists($this->testRoot . '/original.txt'));
    }
}
