<?php

namespace App\Services;

use App\Models\AntivirusScan;
use App\Models\QuarantineFile;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class AntivirusService
{
    public function __construct(
        protected SudoExecutor $sudo
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    //   SYSTEM STATUS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check whether clamscan is installed on this system.
     */
    public function isInstalled(): bool
    {
        try {
            $result = $this->sudo->run(['clamscan', '--version'], checkExit: false);
            return $result->successful() || str_contains($result->stdout . $result->stderr, 'ClamAV');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get ClamAV version string.
     */
    public function getVersion(): string
    {
        try {
            $result = $this->sudo->run(['clamscan', '--version'], checkExit: false);
            $output = trim($result->stdout ?: $result->stderr);
            // First line: "ClamAV 0.103.x/..."
            return explode("\n", $output)[0] ?? 'Desconocida';
        } catch (\Throwable) {
            return 'No disponible';
        }
    }

    /**
     * Get the date of virus definition database.
     */
    public function getDefinitionsInfo(): array
    {
        try {
            $result = $this->sudo->run(['clamscan', '--version'], checkExit: false);
            $output = trim($result->stdout ?: $result->stderr);
            // Format: "ClamAV 0.103.x/26xxx/Day Mon DD HH:MM:SS YYYY"
            if (preg_match('/(\d+)\/(.+)$/', $output, $m)) {
                return [
                    'db_version' => $m[1],
                    'db_date'    => trim($m[2]),
                ];
            }
        } catch (\Throwable) {}

        return ['db_version' => 'N/A', 'db_date' => 'Desconocida'];
    }

    /**
     * Get service status of clamav-daemon.
     */
    public function getDaemonStatus(): string
    {
        try {
            $result = $this->sudo->run(['systemctl', 'is-active', 'clamav-daemon'], checkExit: false);
            return trim($result->stdout) ?: 'inactive';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   SCANNING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Run a recursive scan on the given path.
     * Returns an AntivirusScan model with results persisted to DB.
     */
    public function scan(string $path, bool $withQuarantine = false): AntivirusScan
    {
        $this->validatePath($path);

        $userId = Auth::id();

        // Create pending scan record
        $scan = AntivirusScan::create([
            'user_id'            => $userId,
            'path'               => $path,
            'status'             => 'running',
            'quarantine_enabled' => $withQuarantine,
        ]);

        try {
            if (app()->isProduction()) {
                // Kick off the scan in a detached background process. The web
                // request would otherwise time out on large trees. The worker is
                // spawned as the current process user (www-data in production),
                // so the sudo allowlist for clamscan keeps working.
                $this->spawnBackgroundScan($scan);
            } else {
                // In local/dev, run synchronously so behaviour is predictable.
                $this->executeScan($scan);
            }
        } catch (\Throwable $e) {
            Log::error('AntivirusService::scan failed to launch', ['error' => $e->getMessage()]);
            $scan->update([
                'status'           => 'error',
                'raw_output'       => 'No se pudo lanzar el escaneo: ' . $e->getMessage(),
                'duration_seconds' => 0,
            ]);
        }

        return $scan->fresh();
    }

    /**
     * Launch `antivirus:scan {id}` detached from the web request.
     */
    private function spawnBackgroundScan(AntivirusScan $scan): void
    {
        $php = (new PhpExecutableFinder())->find() ?: 'php';
        $artisan = base_path('artisan');

        $logFile = storage_path('logs/antivirus-scan-' . $scan->id . '.log');

        $command = sprintf(
            'nohup %s %s antivirus:scan %d > %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($artisan),
            $scan->id,
            escapeshellarg($logFile),
        );

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(5);
        $process->run();
    }

    /**
     * Execute the scan for an already-persisted record. Called from the
     * `antivirus:scan` console command (background worker).
     */
    public function executeScan(AntivirusScan $scan): void
    {
        $quarantineDir = config('larapanel.antivirus.quarantine_path', '/var/larapanel/quarantine');
        $timeout       = config('larapanel.antivirus.max_scan_timeout', 300);

        // Build the clamscan command
        $command = [
            'clamscan',
            '--recursive',
            '--infected',         // only print infected files
        ];

        if ($scan->quarantine_enabled) {
            if (!is_dir($quarantineDir)) {
                @mkdir($quarantineDir, 0750, true);
            }
            $command[] = '--move=' . $quarantineDir;
        }

        $command[] = $scan->path;

        $startTime = microtime(true);

        try {
            $result = $this->sudo
                ->withTimeout($timeout)
                ->run($command, checkExit: false);

            $output = trim($result->stdout . "\n" . $result->stderr);

            $summary  = AntivirusScan::parseSummary($output);
            $duration = (int) (microtime(true) - $startTime);

            // clamscan exits with 1 when infected, 2 on error, 0 = clean
            $status = match (true) {
                $result->exitCode === 0                => 'clean',
                $result->exitCode === 1                => 'infected',
                $summary['infected'] > 0               => 'infected',
                default                                => 'error',
            };

            $scan->update([
                'files_scanned'   => $summary['scanned'],
                'infected_count'  => $summary['infected'],
                'error_count'     => $summary['errors'],
                'duration_seconds'=> $duration,
                'status'          => $status,
                'raw_output'      => substr($output, 0, 65535),
            ]);

            // If quarantine was used, parse infected paths and persist them
            if ($scan->quarantine_enabled && $summary['infected'] > 0) {
                $this->persistQuarantinedFiles($output, $scan, $quarantineDir, $scan->user_id);
            }

        } catch (\Throwable $e) {
            $scan->update([
                'status'           => 'error',
                'raw_output'       => $e->getMessage(),
                'duration_seconds' => (int)(microtime(true) - $startTime),
            ]);
            Log::error('AntivirusService::executeScan failed', ['scan_id' => $scan->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Parse clamscan output for infected file lines and persist quarantine records.
     * Format: "/path/to/file: Eicar-Signature FOUND"
     */
    private function persistQuarantinedFiles(string $output, AntivirusScan $scan, string $quarantineDir, int $userId): void
    {
        foreach (explode("\n", $output) as $line) {
            if (!str_contains($line, 'FOUND')) continue;
            // Pattern: "/original/path: ThreatName FOUND"
            if (preg_match('/^(.+?):\s+(.+?)\s+FOUND$/i', trim($line), $m)) {
                $originalPath  = $m[1];
                $threatName    = $m[2];
                $filename      = basename($originalPath);
                $quarantinePath = rtrim($quarantineDir, '/') . '/' . $filename;

                QuarantineFile::create([
                    'user_id'         => $userId,
                    'scan_id'         => $scan->id,
                    'original_path'   => $originalPath,
                    'quarantine_path' => $quarantinePath,
                    'threat_name'     => $threatName,
                    'file_size'       => file_exists($quarantinePath) ? filesize($quarantinePath) : 0,
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   QUARANTINE MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * List quarantine files for the current user.
     */
    public function listQuarantine(?int $userId = null): \Illuminate\Database\Eloquent\Collection
    {
        return QuarantineFile::where('user_id', $userId ?? Auth::id())
            ->latest()
            ->limit(200)
            ->get();
    }

    /**
     * Permanently delete a quarantined file.
     */
    public function deleteFromQuarantine(int $id): bool
    {
        $file = QuarantineFile::where('user_id', Auth::id())->findOrFail($id);

        if (file_exists($file->quarantine_path)) {
            try {
                $this->sudo->run(['rm', '-f', $file->quarantine_path]);
            } catch (\Throwable $e) {
                Log::warning('AntivirusService: could not delete quarantine file', [
                    'path'  => $file->quarantine_path,
                    'error' => $e->getMessage(),
                ]);
                // Try PHP unlink as fallback
                @unlink($file->quarantine_path);
            }
        }

        $file->delete();
        return true;
    }

    /**
     * Restore a quarantined file to its original location.
     */
    public function restoreFromQuarantine(int $id): bool
    {
        $file = QuarantineFile::where('user_id', Auth::id())->findOrFail($id);

        if (!file_exists($file->quarantine_path)) {
            throw new \RuntimeException("El archivo en cuarentena ya no existe en disco.");
        }

        $this->validatePath(dirname($file->original_path));

        try {
            $this->sudo->run(['mv', $file->quarantine_path, $file->original_path]);
            $file->delete();
            return true;
        } catch (\Throwable $e) {
            throw new \RuntimeException("No se pudo restaurar el archivo: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   DEFINITIONS UPDATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Run freshclam to update virus definitions.
     *
     * The clamav-freshclam daemon keeps an exclusive lock on its log file, so a
     * manual `freshclam` run fails with "Failed to lock the log file". We stop
     * the daemon, run the update, then restart it.
     */
    public function updateDefinitions(): string
    {
        $output = '';

        try {
            $this->sudo->run(['systemctl', 'stop', 'clamav-freshclam'], checkExit: false);
        } catch (\Throwable $e) {
            // Daemon may not exist; continue anyway.
            Log::warning('AntivirusService: could not stop clamav-freshclam', ['error' => $e->getMessage()]);
        }

        try {
            $result = $this->sudo
                ->withTimeout(180)
                ->run(['freshclam'], checkExit: false);

            $output = trim($result->stdout . "\n" . $result->stderr);
        } catch (\Throwable $e) {
            $output = "Error al actualizar: " . $e->getMessage();
        }

        try {
            $this->sudo->run(['systemctl', 'start', 'clamav-freshclam'], checkExit: false);
        } catch (\Throwable $e) {
            Log::warning('AntivirusService: could not restart clamav-freshclam', ['error' => $e->getMessage()]);
        }

        return $output;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   SCAN HISTORY
    // ─────────────────────────────────────────────────────────────────────────

    public function scanHistory(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return AntivirusScan::where('user_id', Auth::id())
            ->where('status', '!=', 'running')
            ->latest()
            ->limit($limit)
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function validatePath(string $path): void
    {
        // Prevent path traversal and disallow scanning system-critical directories
        $realPath = realpath($path);
        $blocked  = ['/', '/etc', '/boot', '/proc', '/sys', '/dev'];

        if ($realPath === false) {
            // Path doesn't exist yet — basic check
            if (str_contains($path, '..')) {
                throw new \InvalidArgumentException("Ruta no válida: contiene '..'");
            }
            return;
        }

        if (in_array(rtrim($realPath, '/'), $blocked, true)) {
            throw new \InvalidArgumentException("Ruta bloqueada por seguridad: {$realPath}");
        }
    }
}
