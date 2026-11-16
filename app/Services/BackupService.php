<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\Domain;
use App\Models\DatabaseInstance;
use App\Models\User;
use App\Models\AuditLog;
use App\Notifications\BackupCompletedNotification;
use App\Notifications\BackupFailedNotification;
use App\Services\Notifier;
use App\Shell\SudoExecutor;
use Aws\S3\S3Client;
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

        return config('larapanel.paths.backups', '/var/larapanel/backups') . '/' . $user->id;
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

        $fullPath = null;

        try {
            if (app()->isProduction() && $disk === 's3' && in_array($type, ['full', 'files'], true)) {
                // Backups grandes a S3: se generan y suben en streaming (multipart),
                // sin crear un archivo de decenas de GB en el disco local.
                $streamed   = $this->runStreamingBackupToS3($backup, $user, $data);
                $filename   = $streamed['filename'];
                $remotePath = $streamed['remote_path'];
                $size       = $streamed['size'];
            } else {
                $filename = $this->runBackup($backup, $user, $data);
                $fullPath = $this->getBackupRoot($user) . '/' . $filename;
                $size = file_exists($fullPath) ? filesize($fullPath) : 0;

                $remotePath = null;
                if ($disk === 's3' && file_exists($fullPath)) {
                    $this->configureS3();
                    $remotePath = 'backups/' . $user->id . '/' . $filename;

                    // Upload to S3 (lectura: los archivos son de root vía sudo)
                    \Illuminate\Support\Facades\Storage::disk('s3')->put(
                        $remotePath,
                        fopen($fullPath, 'rb')
                    );

                    // Remove local file (los archivos son de root vía sudo)
                    if (app()->isProduction()) {
                        $this->sudo->run(['rm', '-f', $fullPath], checkExit: false);
                        if ($type === 'full') {
                            // En 'full' el dump SQL quedó embebido en el tar; sobra el staging.
                            $this->sudo->run(['rm', '-f', str_replace('.tar.gz', '.sql.gz', $fullPath)], checkExit: false);
                        }
                    } else {
                        @unlink($fullPath);
                        if ($type === 'full') {
                            @unlink(str_replace('.tar.gz', '.sql.gz', $fullPath));
                        }
                    }
                }
            }

            $backup->update([
                'status'       => 'completed',
                'filename'     => $filename,
                'remote_path'  => $remotePath,
                'size_bytes'   => $size,
                'completed_at' => now(),
            ]);

            AuditLog::record('backup.created', $label, ['type' => $type, 'disk' => $disk, 'size' => $size]);

            Notifier::send(new BackupCompletedNotification($label, $size, $type));
        } catch (\Throwable $e) {
            $backup->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);

            Log::error("BackupService: Backup failed: " . $e->getMessage());

            // Limpieza: si el backup falló NO deben quedar archivos locales
            // (staging) que llenen el disco. Creados por sudo (root).
            if (app()->isProduction() && isset($fullPath)) {
                $this->sudo->run(['rm', '-f', $fullPath], checkExit: false);
                $this->sudo->run(['rm', '-f', $fullPath . '.new'], checkExit: false);
                $this->sudo->run(['rm', '-f', str_replace('.tar.gz', '.sql.gz', $fullPath)], checkExit: false);
            }

            Notifier::send(new BackupFailedNotification($label, $e->getMessage()));

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
            'key' => \App\Models\Setting::getSecret('aws_access_key_id'),
            'secret' => \App\Models\Setting::getSecret('aws_secret_access_key'),
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
     * Build an S3 client from the stored settings (used for multipart streaming).
     */
    protected function buildS3Client(): S3Client
    {
        $endpoint = \App\Models\Setting::get('aws_endpoint');

        return new S3Client([
            'version' => 'latest',
            'region' => \App\Models\Setting::get('aws_default_region', 'us-east-1'),
            'endpoint' => empty($endpoint) ? null : $endpoint,
            'use_path_style_endpoint' => !empty($endpoint),
            'credentials' => [
                'key' => \App\Models\Setting::getSecret('aws_access_key_id'),
                'secret' => \App\Models\Setting::getSecret('aws_secret_access_key'),
            ],
            'http' => [
                'connect_timeout' => 15,
                'timeout' => 0,
            ],
        ]);
    }

    /**
     * Generate a full/files backup and stream it straight to S3.
     *
     * The tar output is read from the process pipe in blocks and uploaded as an
     * S3 multipart upload. This avoids staging a 20+ GB file on the local disk,
     * which is what made full backups fail when free space was tight.
     *
     * @return array{filename:string,remote_path:string,size:int}
     */
    protected function runStreamingBackupToS3(Backup $backup, User $user, array $data): array
    {
        $type      = $backup->type;
        $domainId  = $data['domain_id'] ?? null;
        $timestamp = now()->format('Ymd_His');

        $domain     = null;
        $domainSlug = 'all';
        if ($domainId) {
            $domain = Domain::find($domainId);
            $domainSlug = $domain ? Str::slug($domain->name, '_') : 'domain_' . $domainId;
        }

        $filename   = "larapanel_{$type}_{$domainSlug}_{$timestamp}.tar.gz";
        $remotePath = 'backups/' . $user->id . '/' . $filename;

        $tmpDir = sys_get_temp_dir() . '/lp_stream_' . uniqid();
        $this->sudo->run(['mkdir', '-p', $tmpDir]);
        $this->sudo->run(['chmod', '755', $tmpDir]);

        try {
            $tarArgs = ['tar', '-czf', '-'];

            // Incluir el dump SQL en backups full (staging pequeño en /tmp).
            if ($type === 'full') {
                $dumpName = 'database_dump.sql.gz';
                $dumpPath = $tmpDir . '/' . $dumpName;
                $this->backupDatabases($dumpPath, $domainId, $user, $data);

                if ($this->sudo->run(['test', '-f', $dumpPath], checkExit: false)->successful()) {
                    $tarArgs[] = '-C';
                    $tarArgs[] = $tmpDir;
                    $tarArgs[] = $dumpName;
                }
            }

            $sourcePath = $domain
                ? $domain->document_root
                : config('larapanel.paths.webroots', '/var/www');

            if (! $this->sudo->run(['test', '-d', $sourcePath], checkExit: false)->successful()) {
                throw new \RuntimeException("El directorio de origen no existe: {$sourcePath}");
            }

            $tarArgs[] = '-C';
            $tarArgs[] = dirname($sourcePath);
            $tarArgs[] = basename($sourcePath);

            // Los buzones solo se incluyen en un full de todas las cuentas.
            if ($type === 'full' && ! $domain
                && $this->sudo->run(['test', '-d', '/var/vmail'], checkExit: false)->successful()) {
                $tarArgs[] = '-C';
                $tarArgs[] = '/var';
                $tarArgs[] = 'vmail';
            }

            $size = $this->streamCommandToS3(array_merge(['sudo', '-n'], $tarArgs), $remotePath);

            return [
                'filename'    => $filename,
                'remote_path' => $remotePath,
                'size'        => $size,
            ];
        } finally {
            $this->sudo->run(['rm', '-rf', $tmpDir], checkExit: false);
        }
    }

    /**
     * Run a command whose stdout is a tar stream and upload it to S3 in parts.
     *
     * @return int uploaded bytes
     */
    protected function streamCommandToS3(array $command, string $remotePath): int
    {
        $client = $this->buildS3Client();
        $bucket = \App\Models\Setting::get('aws_bucket');

        if (empty($bucket)) {
            throw new \RuntimeException('No hay un bucket S3 configurado.');
        }

        $stderrFile = tempnam(sys_get_temp_dir(), 'lp_s3err_');

        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['file', $stderrFile, 'w'],
        ], $pipes);

        if (! is_resource($process)) {
            @unlink($stderrFile);
            throw new \RuntimeException('No se pudo iniciar el proceso de backup.');
        }

        $uploadId   = null;
        $parts      = [];
        $partNumber = 1;
        $buffer     = '';
        $minPart    = 32 * 1024 * 1024; // 32 MiB (> 5 MiB mínimo de S3)
        $total      = 0;

        try {
            $uploadId = $client->createMultipartUpload([
                'Bucket' => $bucket,
                'Key'    => $remotePath,
            ])['UploadId'];

            while (! feof($pipes[1])) {
                $chunk = fread($pipes[1], 1024 * 1024);
                if ($chunk === false) {
                    throw new \RuntimeException('Error leyendo el stream del backup.');
                }
                if ($chunk === '') {
                    continue;
                }

                $buffer .= $chunk;
                $total  += strlen($chunk);

                if (strlen($buffer) >= $minPart) {
                    $result = $client->uploadPart([
                        'Bucket'     => $bucket,
                        'Key'        => $remotePath,
                        'UploadId'   => $uploadId,
                        'PartNumber' => $partNumber,
                        'Body'       => $buffer,
                    ]);
                    $parts[] = ['PartNumber' => $partNumber, 'ETag' => $result['ETag']];
                    $partNumber++;
                    $buffer = '';
                }
            }

            if ($buffer !== '') {
                $result = $client->uploadPart([
                    'Bucket'     => $bucket,
                    'Key'        => $remotePath,
                    'UploadId'   => $uploadId,
                    'PartNumber' => $partNumber,
                    'Body'       => $buffer,
                ]);
                $parts[] = ['PartNumber' => $partNumber, 'ETag' => $result['ETag']];
                $buffer = '';
            }

            fclose($pipes[1]);
            $exitCode = proc_close($process);
            $stderr = trim((string) @file_get_contents($stderrFile));

            // GNU tar devuelve 1 cuando algún archivo cambió mientras se leía
            // (logs, buzones, .git). Eso no invalida el archivo: solo 2+ es fatal.
            if ($exitCode < 0 || $exitCode >= 2) {
                throw new \RuntimeException(
                    'El comando de backup falló (código ' . $exitCode . '): ' . $stderr
                );
            }

            if ($exitCode === 1 && $stderr !== '') {
                Log::warning('Backup completado con advertencias de tar: ' . Str::limit($stderr, 2000));
            }

            if ($total === 0) {
                throw new \RuntimeException('El backup generó un archivo vacío.');
            }

            $client->completeMultipartUpload([
                'Bucket'          => $bucket,
                'Key'             => $remotePath,
                'UploadId'        => $uploadId,
                'MultipartUpload' => ['Parts' => $parts],
            ]);

            return $total;
        } catch (\Throwable $e) {
            if ($uploadId) {
                try {
                    $client->abortMultipartUpload([
                        'Bucket'   => $bucket,
                        'Key'      => $remotePath,
                        'UploadId' => $uploadId,
                    ]);
                } catch (\Throwable) {
                    // Ignorar: el abort es best-effort.
                }
            }

            if (is_resource($process)) {
                @proc_terminate($process);
                @proc_close($process);
            }

            throw $e;
        } finally {
            @unlink($stderrFile);
        }
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
                $this->sudo->run(['chmod', '755', $backupRoot]);
            } else {
                @mkdir($backupRoot, 0755, true);
            }
        }

        if (app()->isProduction() && is_dir($backupRoot)) {
            // Limpiar restos de backups interrumpidos (procesos muertos por OOM/disco lleno).
            foreach (glob($backupRoot . '/*.new') ?: [] as $stale) {
                if (@filemtime($stale) < time() - 3600) {
                    $this->sudo->run(['rm', '-f', $stale], checkExit: false);
                }
            }
        }

        // Un backup full se genera en una sola pasada (archivos + buzones + dump).
        // Antes se creaba primero un tar solo de archivos y luego el tar final,
        // duplicando el espacio necesario en disco.
        if (app()->isProduction() && $type === 'full') {
            $lastSize = (int) Backup::where('user_id', $user->id)
                ->where('type', 'full')
                ->where('status', 'completed')
                ->latest('id')
                ->value('size_bytes');
            // El pico real en disco es el tar final (~$lastSize) más el dump SQL
            // que queda como staging (~cientos de MB), no un 25% extra. Se deja
            // 1 GiB (o 5% del tamaño) de margen para logs/BD durante la copia.
            $margin   = max(1024 ** 3, (int) ceil($lastSize * 0.05));
            $required = max(5 * 1024 ** 3, $lastSize + $margin);
            $free     = @disk_free_space($backupRoot) ?: 0;

            if ($free < $required) {
                throw new \RuntimeException(sprintf(
                    'Espacio insuficiente para backup full: libre %.2f GB, requerido ~%.2f GB.',
                    $free / 1073741824,
                    $required / 1073741824
                ));
            }
        }

        if ($type === 'files' || ($type === 'full' && !app()->isProduction())) {
            $this->backupFiles($destPath, $domainId, $user, $data);
        }

        if ($type === 'database' || $type === 'full') {
            $dbFilename = str_replace('.tar.gz', '.sql.gz', $destPath);
            $this->backupDatabases($dbFilename, $domainId, $user, $data);

            // If full backup, combine both into one archive
            if ($type === 'full' && file_exists($dbFilename)) {
                $tmpDir = sys_get_temp_dir() . '/lp_backup_' . uniqid();

                if (app()->isProduction()) {
                    // Los archivos y el dump fueron creados por sudo (root).
                    // Operamos todo con sudo para no fallar por permisos.
                    $sqlDest = $tmpDir . '/database_dump.sql.gz';
                    $this->sudo->run(['mkdir', '-p', $tmpDir]);
                    $this->sudo->run(['chmod', '755', $tmpDir]);
                    $this->sudo->run(['cp', $dbFilename, $sqlDest]);

                    // Armar el archivo definitivo: dump SQL + archivos web (www) + buzones (vmail)
                    $tarArgs = [
                        'tar', '-czf', $destPath . '.new',
                        '-C', $tmpDir, 'database_dump.sql.gz',
                        '-C', '/var', 'www',
                    ];

                    // Incluir buzones de correo si el servidor tiene email (/var/vmail)
                    if ($this->sudo->run(['test', '-d', '/var/vmail'], checkExit: false)->successful()) {
                        $tarArgs[] = '-C';
                        $tarArgs[] = '/var';
                        $tarArgs[] = 'vmail';
                    }

                    $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
                        ->run($tarArgs);
                    $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
                        ->run(['mv', $destPath . '.new', $destPath]);
                    $this->sudo->run(['chmod', '755', $destPath]);

                    $this->sudo->run(['rm', '-rf', $tmpDir], checkExit: false);
                } else {
                    // Dev: incorporar el dump al archivo de archivos en un zip
                    $combinedDest = $destPath . '.comb';
                    @mkdir($tmpDir, 0700, true);
                    @copy($dbFilename, $tmpDir . '/database_dump.sql.gz');
                    @rename($destPath, $tmpDir . '/files.tar.gz');

                    $zip = new \ZipArchive();
                    if ($zip->open($combinedDest, \ZipArchive::CREATE) === true) {
                        foreach (glob($tmpDir . '/*') as $f) {
                            $zip->addFile($f, basename($f));
                        }
                        $zip->close();
                        @rename($combinedDest, $destPath);
                    }
                    @unlink($tmpDir . '/database_dump.sql.gz');
                    @unlink($tmpDir . '/files.tar.gz');
                    @unlink($tmpDir . '/files.tar');
                    @rmdir($tmpDir);
                }
            } elseif ($type === 'full') {
                // Sin bases de datos: el backup full contiene solo archivos.
                $this->backupFiles($destPath, $domainId, $user, $data);
            }
        }

        // Un backup de solo base de datos se guarda como .sql.gz, no como .tar.gz.
        if ($type === 'database') {
            return str_replace('.tar.gz', '.sql.gz', $filename);
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

        $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
            ->run(['tar', '-czf', $destPath, '-C', dirname($sourcePath), basename($sourcePath)]);
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

        $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
            ->run(['mysqldump', '--databases', ...$databases, '--result-file=' . $tmpSql]);
        $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
            ->run(['gzip', $tmpSql]);
        $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
            ->run(['mv', $tmpSql . '.gz', $destPath]);
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
                $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
                    ->run(['tar', '-xzf', $localPath, '-C', dirname($docRoot)]);
            }

            // Restore mailboxes (solo backups "full all": el archivo incluye vmail/)
            if ($type === 'full' && !$domain) {
                $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
                    ->run(['tar', '-xzf', $localPath, '-C', '/var', 'vmail'], checkExit: false);
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
                    
                    $sqlGzPath = $tmpDir . '/database_dump.sql.gz';

                    if (str_ends_with(strtolower($localPath), '.sql.gz')) {
                        // Backup de solo base de datos: el archivo ya es el dump.
                        $this->sudo->run(['cp', $localPath, $sqlGzPath]);
                    } else {
                        // El tar contiene un archivo llamado database_dump.sql.gz.
                        $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
                            ->run(['tar', '-xzf', $localPath, '-C', $tmpDir, 'database_dump.sql.gz'], checkExit: false);
                    }

                    if (file_exists($sqlGzPath) || $this->sudo->run(['test', '-f', $sqlGzPath], false)->successful()) {
                        // Unzip and restore
                        // We assume the dump already contains `USE db_name;` or we just source it.
                        // mysqldump --databases includes CREATE DATABASE and USE statements by default!
                        $this->sudo->run(['gunzip', $sqlGzPath]);
                        $sqlPath = $tmpDir . '/database_dump.sql';
                        $sql = file_get_contents($sqlPath);
                        if ($sql === false) {
                            throw new \RuntimeException('No se pudo leer el dump SQL restaurado.');
                        }
                        $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
                            ->withInput($sql)->run(['mysql']);
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
