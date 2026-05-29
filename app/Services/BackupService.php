<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\Domain;
use App\Models\DatabaseInstance;
use App\Models\User;
use App\Models\AuditLog;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BackupService
{
    public function __construct(
        protected SudoExecutor $sudo,
    ) {}

    /**
     * Get the root directory where backups are stored for a user.
     */
    public function getBackupRoot(User $user): string
    {
        if (!app()->isProduction()) {
            $path = storage_path('app/public/backups/' . $user->id);
            if (!is_dir($path)) {
                @mkdir($path, 0700, true);
            }
            return $path;
        }

        return config('larapanel.paths.backups', '/var/backups/larapanel') . '/' . $user->id;
    }

    /**
     * Create a new backup (files only, database only, or full).
     */
    public function create(User $user, array $data): Backup
    {
        $type       = $data['type'] ?? 'full';        // full | files | database
        $domainId   = $data['domain_id'] ?? null;
        $label      = $data['label'] ?? 'Backup ' . now()->format('d/m/Y H:i');
        $notes      = $data['notes'] ?? null;

        $backup = Backup::create([
            'user_id'    => $user->id,
            'domain_id'  => $domainId,
            'label'      => $label,
            'type'       => $type,
            'status'     => 'running',
            'notes'      => $notes,
            'started_at' => now(),
        ]);

        try {
            $filename = $this->runBackup($backup, $user, $data);

            $fullPath = $this->getBackupRoot($user) . '/' . $filename;
            $size = file_exists($fullPath) ? filesize($fullPath) : 0;

            $backup->update([
                'status'       => 'completed',
                'filename'     => $filename,
                'size_bytes'   => $size,
                'completed_at' => now(),
            ]);

            AuditLog::record('backup.created', $label, ['type' => $type, 'size' => $size]);
        } catch (\Throwable $e) {
            $backup->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);

            Log::error("BackupService: Backup failed: " . $e->getMessage());
            throw $e;
        }

        return $backup->fresh();
    }

    /**
     * Internal method that performs the actual backup.
     */
    protected function runBackup(Backup $backup, User $user, array $data): string
    {
        $type       = $backup->type;
        $backupRoot = $this->getBackupRoot($user);
        $timestamp  = now()->format('Ymd_His');
        $domainId   = $data['domain_id'] ?? null;

        // Resolve domain name slug for filename
        $domainSlug = 'all';
        if ($domainId) {
            $domain = Domain::find($domainId);
            $domainSlug = $domain ? Str::slug($domain->name, '_') : 'domain_' . $domainId;
        }

        $filename = "larapanel_{$type}_{$domainSlug}_{$timestamp}.tar.gz";
        $destPath = $backupRoot . '/' . $filename;

        if (!is_dir($backupRoot)) {
            if (app()->isProduction()) {
                $this->sudo->run(['mkdir', '-p', $backupRoot]);
                $this->sudo->run(['chmod', '700', $backupRoot]);
            } else {
                @mkdir($backupRoot, 0700, true);
            }
        }

        if ($type === 'files' || $type === 'full') {
            $this->backupFiles($destPath, $domainId, $user, $data);
        }

        if ($type === 'database' || $type === 'full') {
            $dbFilename = str_replace('.tar.gz', '.sql.gz', $destPath);
            $this->backupDatabases($dbFilename, $domainId, $user, $data);

            // If full backup, combine both into one archive
            if ($type === 'full' && file_exists($dbFilename)) {
                // Append SQL dump to the main archive
                // Create temp dir for combining
                $tmpDir = sys_get_temp_dir() . '/lp_backup_' . uniqid();
                @mkdir($tmpDir, 0700, true);

                // Move sql.gz into temp dir then add to archive
                $sqlDest = $tmpDir . '/database_dump.sql.gz';
                @rename($dbFilename, $sqlDest);

                if (app()->isProduction()) {
                    // Append existing sql file to tar
                    $this->sudo->run(['tar', '-czf', $destPath . '.new', '-C', $tmpDir, '.']);
                    $this->sudo->run(['mv', $destPath . '.new', $destPath]);
                } else {
                    @rename($sqlDest, $dbFilename);
                }

                @unlink($tmpDir . '/database_dump.sql.gz');
                @rmdir($tmpDir);
            }
        }

        return $filename;
    }

    /**
     * Backup files for a domain or all domains.
     */
    protected function backupFiles(string $destPath, ?int $domainId, User $user, array $data): void
    {
        $webroot = config('larapanel.paths.webroots', '/var/www');

        if ($domainId) {
            $domain = Domain::findOrFail($domainId);
            $sourcePath = $domain->document_root;
        } else {
            // Backup all domains for this user
            $sourcePath = $webroot;
        }

        if (!app()->isProduction()) {
            // In dev, just create a dummy tar.gz
            $devRoot = storage_path('app/public/webroot');
            if (!is_dir($devRoot)) {
                @mkdir($devRoot, 0755, true);
                @file_put_contents($devRoot . '/index.html', '<html><body>Dev backup test</body></html>');
            }

            $zip = new \ZipArchive();
            $zipPath = str_replace('.tar.gz', '.zip', $destPath);
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $zip->addFromString('index.html', '<html><body>LaraPanel Dev Backup</body></html>');
                $zip->addFromString('README.txt', "Backup created at: " . now()->toDateTimeString());
                $zip->close();
            }

            // Simulate tar.gz by creating an empty file
            file_put_contents($destPath, 'DEV_BACKUP:' . base64_encode(file_get_contents($zipPath)));
            @unlink($zipPath);
            return;
        }

        if (!file_exists($sourcePath)) {
            throw new \RuntimeException("El directorio de origen no existe: {$sourcePath}");
        }

        $this->sudo->run(['tar', '-czf', $destPath, '-C', dirname($sourcePath), basename($sourcePath)]);
    }

    /**
     * Dump databases for a domain or all databases for this user.
     */
    protected function backupDatabases(string $destPath, ?int $domainId, User $user, array $data): void
    {
        if (!app()->isProduction()) {
            file_put_contents($destPath, 'DEV_SQL_DUMP:' . now()->toIso8601String());
            return;
        }

        $databases = DatabaseInstance::where('user_id', $user->id)
            ->when($domainId, fn($q) => $q->where('domain_id', $domainId))
            ->pluck('db_name')
            ->toArray();

        if (empty($databases)) {
            return;
        }

        $dbArgs = implode(' ', array_map('escapeshellarg', $databases));
        $tmpSql = sys_get_temp_dir() . '/lp_sql_' . uniqid() . '.sql';

        $this->sudo->run(['mysqldump', '--databases', ...$databases, '--result-file=' . $tmpSql]);
        $this->sudo->run(['gzip', '-c', $tmpSql, '>', $destPath]);
        @unlink($tmpSql);
    }

    /**
     * Delete a backup record and its file.
     */
    public function delete(Backup $backup): void
    {
        if ($backup->filename) {
            $fullPath = $this->getBackupRoot($backup->user) . '/' . $backup->filename;
            if (file_exists($fullPath)) {
                if (app()->isProduction()) {
                    $this->sudo->run(['rm', '-f', $fullPath], checkExit: false);
                } else {
                    @unlink($fullPath);
                }
            }
        }

        AuditLog::record('backup.deleted', $backup->label);
        $backup->delete();
    }

    /**
     * Get full path of backup file for download.
     */
    public function getFullPath(Backup $backup): ?string
    {
        if (!$backup->filename) return null;
        $path = $this->getBackupRoot($backup->user) . '/' . $backup->filename;
        return file_exists($path) ? $path : null;
    }

    /**
     * List all backup files with their sizes for a user.
     */
    public function listFiles(User $user): array
    {
        $root = $this->getBackupRoot($user);
        if (!is_dir($root)) return [];

        $files = [];
        foreach (glob($root . '/larapanel_*.{tar.gz,zip,sql.gz}', GLOB_BRACE) as $file) {
            $files[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'modified' => filemtime($file),
            ];
        }
        return $files;
    }
}
