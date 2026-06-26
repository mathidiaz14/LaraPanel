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

        $disk       = $data['disk'] ?? 'local';

        $backup = Backup::create([
            'user_id'    => $user->id,
            'domain_id'  => $domainId,
            'label'      => $label,
            'type'       => $type,
            'disk'       => $disk,
            'status'     => 'running',
            'notes'      => $notes,
            'started_at' => now(),
        ]);

        try {
            $filename = $this->runBackup($backup, $user, $data);
            $fullPath = $this->getBackupRoot($user) . '/' . $filename;
            $size = file_exists($fullPath) ? filesize($fullPath) : 0;
            
            $remotePath = null;
            if ($disk === 's3' && file_exists($fullPath)) {
                $this->configureS3();
                $remotePath = 'backups/' . $user->id . '/' . $filename;
                
                // Upload to S3
                \Illuminate\Support\Facades\Storage::disk('s3')->put(
                    $remotePath,
                    fopen($fullPath, 'r+')
                );
                
                // Remove local file
                @unlink($fullPath);
            }

            $backup->update([
                'status'       => 'completed',
                'filename'     => $filename,
                'remote_path'  => $remotePath,
                'size_bytes'   => $size,
                'completed_at' => now(),
            ]);

            AuditLog::record('backup.created', $label, ['type' => $type, 'disk' => $disk, 'size' => $size]);
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
     * Configure S3 filesystem disk using global settings.
     */
    protected function configureS3(): void
    {
        $endpoint = \App\Models\Setting::get('aws_endpoint');
        
        $config = [
            'driver' => 's3',
            'key' => \App\Models\Setting::get('aws_access_key_id'),
            'secret' => \App\Models\Setting::get('aws_secret_access_key'),
            'region' => \App\Models\Setting::get('aws_default_region', 'us-east-1'),
            'bucket' => \App\Models\Setting::get('aws_bucket'),
            'url' => null,
            'endpoint' => empty($endpoint) ? null : $endpoint,
            'use_path_style_endpoint' => !empty($endpoint),
            'throw' => false,
        ];
        
        config(['filesystems.disks.s3' => $config]);
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
        $this->sudo->run(['gzip', $tmpSql]);
        $this->sudo->run(['mv', $tmpSql . '.gz', $destPath]);
    }

    /**
     * Delete a backup record and its file.
     */
    public function delete(Backup $backup): void
    {
        if ($backup->disk === 's3' && $backup->remote_path) {
            $this->configureS3();
            if (\Illuminate\Support\Facades\Storage::disk('s3')->exists($backup->remote_path)) {
                \Illuminate\Support\Facades\Storage::disk('s3')->delete($backup->remote_path);
            }
        } elseif ($backup->filename) {
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
     * Restore a backup.
     */
    public function restore(Backup $backup): void
    {
        $localPath = $this->getFullPath($backup, true); // true forces download if s3
        
        if (!$localPath || !file_exists($localPath)) {
            throw new \RuntimeException("No se pudo encontrar o descargar el archivo de backup.");
        }
        
        if (!app()->isProduction()) {
            AuditLog::record('backup.restored', $backup->label . ' (Simulado)');
            return;
        }
        
        $type = $backup->type;
        $domain = $backup->domain;
        
        try {
            // Restore Files
            if (($type === 'files' || $type === 'full') && $domain) {
                // Extract directly to webroot directory, replacing files
                $docRoot = $domain->document_root;
                $this->sudo->run(['tar', '-xzf', $localPath, '-C', dirname($docRoot)]);
            }

            // Restore Database
            if (($type === 'database' || $type === 'full')) {
                // Determine databases included in this backup based on domain
                $databases = DatabaseInstance::where('user_id', $backup->user_id)
                    ->when($domain, fn($q) => $q->where('domain_id', $domain->id))
                    ->pluck('db_name')
                    ->toArray();
                
                if (!empty($databases)) {
                    // Extract the sql.gz from the tar archive to a temp directory
                    $tmpDir = sys_get_temp_dir() . '/lp_restore_' . uniqid();
                    $this->sudo->run(['mkdir', '-p', $tmpDir]);
                    
                    // The tar has a file named database_dump.sql.gz inside
                    $this->sudo->run(['tar', '-xzf', $localPath, '-C', $tmpDir, 'database_dump.sql.gz'], checkExit: false);
                    
                    $sqlGzPath = $tmpDir . '/database_dump.sql.gz';
                    if (file_exists($sqlGzPath) || $this->sudo->run(['test', '-f', $sqlGzPath], false)->successful()) {
                        // Unzip and restore
                        // We assume the dump already contains `USE db_name;` or we just source it.
                        // mysqldump --databases includes CREATE DATABASE and USE statements by default!
                        $this->sudo->run(['gunzip', $sqlGzPath]);
                        $sqlPath = $tmpDir . '/database_dump.sql';
                        $this->sudo->run(['mysql', '<', $sqlPath]);
                    }
                    
                    $this->sudo->run(['rm', '-rf', $tmpDir]);
                }
            }
            
            AuditLog::record('backup.restored', $backup->label);
            
        } catch (\Throwable $e) {
            Log::error("Error restaurando backup {$backup->id}: " . $e->getMessage());
            throw new \RuntimeException("Error crítico al restaurar: " . $e->getMessage());
        }
    }

    /**
     * Get full path of backup file for download.
     */
    public function getFullPath(Backup $backup, bool $forceDownload = false): ?string
    {
        if ($backup->disk === 's3' && $backup->remote_path) {
            $this->configureS3();
            if ($forceDownload) {
                // Download to local tmp
                $tmpPath = sys_get_temp_dir() . '/' . basename($backup->remote_path);
                if (!file_exists($tmpPath)) {
                    $stream = \Illuminate\Support\Facades\Storage::disk('s3')->readStream($backup->remote_path);
                    if ($stream) {
                        file_put_contents($tmpPath, stream_get_contents($stream));
                        fclose($stream);
                    }
                }
                return file_exists($tmpPath) ? $tmpPath : null;
            }
            
            // Just returning a temporary URL for download
            return \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl(
                $backup->remote_path,
                now()->addMinutes(60)
            );
        }
        
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
