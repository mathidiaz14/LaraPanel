<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Backup;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BackupVerificationService
{
    public function __construct(
        protected SudoExecutor $sudo,
        protected BackupService $backupService,
    ) {}

    /**
     * Verify the integrity of a database backup by restoring it into a scratch
     * MySQL database. Returns ['verified'=>bool, 'message'=>string, 'tables'=>int, 'duration_ms'=>int].
     */
    public function verify(Backup $backup): array
    {
        $started = microtime(true);
        $duration = fn (): int => (int) round((microtime(true) - $started) * 1000);

        if ($backup->type !== 'database') {
            return $this->record($backup, [
                'verified'   => false,
                'message'    => 'Solo se pueden verificar backups de tipo Base de Datos.',
                'tables'     => 0,
                'duration_ms'=> $duration(),
            ], 'info', false);
        }

        $localPath = $this->backupService->getFullPath($backup, true);
        if (! $localPath || ! is_file($localPath)) {
            return $this->record($backup, [
                'verified'   => false,
                'message'    => 'No se encontró el archivo de backup en el servidor.',
                'tables'     => 0,
                'duration_ms'=> $duration(),
            ], 'warning');
        }

        if (! app()->isProduction()) {
            return $this->record($backup, [
                'verified'   => true,
                'message'    => 'Verificación simulada correctamente (entorno de desarrollo).',
                'tables'     => 0,
                'duration_ms'=> $duration(),
            ]);
        }

        // Lowercase to stay within MySQL's table_schema case-insensitivity.
        $scratch = 'lp_verify_' . Str::lower(Str::random(10));
        $workDir = sys_get_temp_dir() . '/lp_verify_' . Str::random(8);

        try {
            $sqlPath = $this->prepareSqlDump($localPath, $workDir);

            // Make sure the scratch database (and the work dir state) is clean.
            $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
                ->run(['mysql', '-e', "DROP DATABASE IF EXISTS `{$scratch}`;"]);

            $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
                ->run(['mysql', '-e', "CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"]);

            $sql = file_get_contents($sqlPath);
            if ($sql === false) {
                throw new \RuntimeException('No se pudo leer el dump SQL preparado.');
            }

            $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
                ->withInput($sql)
                ->run(['mysql', $scratch]);

            $count = $this->sudo->run([
                'mysql', '-N', '-B', '-e',
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$scratch}';"
            ]);
            $tables = max(0, (int) trim($count->stdout));

            return $this->record($backup, [
                'verified'   => true,
                'message'    => "Importación de prueba satisfactoria: {$tables} tabla(s) restaurada(s).",
                'tables'     => $tables,
                'duration_ms'=> $duration(),
            ]);
        } catch (\Throwable $e) {
            Log::error("BackupVerificationService: falló la verificación del backup {$backup->id}: " . $e->getMessage());

            return $this->record($backup, [
                'verified'   => false,
                'message'    => 'La verificación falló: ' . $e->getMessage(),
                'tables'     => 0,
                'duration_ms'=> $duration(),
            ], 'warning');
        } finally {
            // Always clean up the scratch database and work directory.
            try {
                $this->sudo->run(['mysql', '-e', "DROP DATABASE IF EXISTS `{$scratch}`;"], checkExit: false);
            } catch (\Throwable) {
            }
            try {
                $this->sudo->run(['rm', '-rf', $workDir], checkExit: false);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Verify the most recent N database backups. Returns the verifications.
     */
    public function verifyLatest(int $limit = 10): array
    {
        $backups = Backup::where('type', 'database')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $results = [];
        foreach ($backups as $backup) {
            $result = $this->verify($backup);
            $results[] = [
                'backup_id' => $backup->id,
                'label'     => $backup->label,
                'filename'  => $backup->filename,
            ] + $result;
        }

        $verified = collect($results)->where('verified', true)->count();

        AuditLog::record('backup.verify.batch', "Verificación de {$backups->count()} backups de base de datos", [
            'limit'    => $limit,
            'verified' => $verified,
            'failed'   => count($results) - $verified,
        ]);

        return $results;
    }

    /**
     * Return a plain-text SQL dump path for import. Backups created by
     * BackupService are stored as .sql.gz files, so a temporary copy is
     * decompressed before being handed to the mysql client.
     */
    protected function prepareSqlDump(string $localPath, string $workDir): string
    {
        if (str_ends_with(strtolower($localPath), '.sql.gz')
            || str_ends_with(strtolower($localPath), '.gz')) {
            $this->sudo->run(['mkdir', '-p', $workDir]);

            $gzCopy = $workDir . '/dump.sql.gz';
            $sqlPath = $workDir . '/dump.sql';

            $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
                ->run(['cp', $localPath, $gzCopy]);
            $this->sudo->withTimeout(config('larapanel.backups.timeout', 3600))
                ->run(['gunzip', $gzCopy]);

            if (! is_file($sqlPath) || ! is_readable($sqlPath)) {
                throw new \RuntimeException('No se pudo descomprimir el dump SQL.');
            }

            return $sqlPath;
        }

        return $localPath;
    }

    /**
     * Persist the verification result on the Backup record and the audit log.
     */
    protected function record(Backup $backup, array $result, string $severity = 'info', bool $persistAudit = true): array
    {
        $status = $result['verified'] ? 'verified' : 'failed';

        $backup->update([
            'verification_status' => $status,
            'verified_at'         => now(),
        ]);

        if ($persistAudit) {
            AuditLog::record(
                $result['verified'] ? 'backup.verified' : 'backup.verification_failed',
                $backup->label,
                [
                    'backup_id'  => $backup->id,
                    'filename'   => $backup->filename,
                    'tables'     => $result['tables'],
                    'duration_ms'=> $result['duration_ms'],
                    'message'    => $result['message'],
                ],
                $severity,
            );
        }

        return $result;
    }
}