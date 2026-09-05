<?php

namespace App\Services;

use App\Models\RemoteFtpConnection;
use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Log;

class RemoteFtpService
{
    public const JOB_ROOT = 'app/remote-ftp-jobs';

    public function __construct(protected SudoExecutor $sudo) {}

    /**
     * Open an interactive FTP/FTPS session (PHP native functions).
     *
     * @return resource
     */
    public function connect(RemoteFtpConnection $conn, ?string $explicitPassword = null)
    {
        $this->assertSafeHost($conn->host);
        $this->assertSafeUsername($conn->username);

        $password = $explicitPassword ?? $conn->password;
        if ($password === null || $password === '') {
            throw new \RuntimeException('Se requiere la contraseña para conectar.');
        }

        $connection = $conn->protocol === 'ftps'
            ? @ftp_ssl_connect($conn->host, $conn->port, 30)
            : @ftp_connect($conn->host, $conn->port, 30);

        if ($connection === false) {
            throw new \RuntimeException("No se pudo conectar a {$conn->host}:{$conn->port}.");
        }

        @ftp_set_option($connection, FTP_TIMEOUT_SEC, 30);

        if (!@ftp_login($connection, $conn->username, $password)) {
            @ftp_close($connection);
            throw new \RuntimeException('Autenticación fallida: usuario o contraseña incorrectos.');
        }

        if ($conn->passive) {
            @ftp_pasv($connection, true);
        }

        $initial = trim($conn->initial_path);
        if ($initial !== '' && $initial !== '/') {
            @ftp_chdir($connection, $initial);
        }

        return $connection;
    }

    public function pwd($connection): string
    {
        $pwd = @ftp_pwd($connection);
        return is_string($pwd) ? $pwd : '/';
    }

    public function cd($connection, string $path): bool
    {
        if ($path === '' || $path === '/') {
            return false;
        }
        return @ftp_chdir($connection, $path);
    }

    /**
     * List a directory using LIST -aL and parse standard UNIX long format.
     *
     * @return array<int, array{name:string, type:'file'|'dir'|'link', size:int, mtime:int}>
     */
    public function list($connection, string $path): array
    {
        $raw = @ftp_rawlist($connection, '-al ' . $path);
        if ($raw === false || $raw === []) {
            return [];
        }

        $entries = [];
        foreach ($raw as $line) {
            $parsed = $this->parseRawListLine((string) $line);
            if ($parsed === null) {
                continue;
            }
            $name = $parsed['name'];
            if ($name === '.' || $name === '..') {
                continue;
            }
            $entries[] = $parsed;
        }

        // Folders first, then alphabetical.
        usort($entries, fn ($a, $b) => [$a['type'] !== 'dir', strtolower($a['name'])] <=> [$b['type'] !== 'dir', strtolower($b['name'])]);

        return $entries;
    }

    private function parseRawListLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, 'total')) {
            return null;
        }

        $parts = preg_split('/\s+/', $line);
        if ($parts === false || count($parts) < 9) {
            return null;
        }

        $perms = $parts[0];
        $type = match (true) {
            str_starts_with($perms, 'd') => 'dir',
            str_starts_with($perms, 'l') => 'link',
            default                       => 'file',
        };

        $size = (int) ($parts[4] ?? 0);
        $mtime = strtotime(($parts[5] ?? '') . ' ' . ($parts[6] ?? '') . ' ' . ($parts[7] ?? ''));

        $name = implode(' ', array_slice($parts, 8));
        if (str_contains($name, ' -> ')) {
            [$name] = explode(' -> ', $name, 2);
        }

        if ($name === '') {
            return null;
        }

        return [
            'name'  => $name,
            'type'  => $type,
            'size'  => $type === 'file' ? $size : 0,
            'mtime' => is_int($mtime) ? $mtime : 0,
        ];
    }

    /**
     * Stream download a single remote file to a local staging path.
     */
    public function downloadFile($connection, string $remotePath, string $stagingDir): string
    {
        $fileName = basename($remotePath);
        if ($fileName === '' || $fileName === '/' || $fileName === '.') {
            throw new \RuntimeException('Ruta de archivo inválida.');
        }

        if (!is_dir($stagingDir)) {
            @mkdir($stagingDir, 0770, true);
        }

        $localPath = rtrim($stagingDir, '/') . '/' . $fileName;
        $handle = @fopen($localPath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal.');
        }

        $ok = @ftp_fget($connection, $handle, $remotePath, FTP_BINARY, 0);
        @fclose($handle);

        if (!$ok) {
            @unlink($localPath);
            throw new \RuntimeException("Fallo al descargar el archivo remoto: {$remotePath}");
        }

        return $localPath;
    }

    /**
     * Move a staged/downloaded file into a final server location (root via sudo).
     */
    public function moveInto(string $stagedPath, string $targetDir, string $targetName): void
    {
        if (!is_file($stagedPath)) {
            throw new \RuntimeException('El archivo temporal ya no existe.');
        }

        $targetDir = $this->assertSafeLocalTarget($targetDir);
        $this->assertSafeBaseName($targetName);

        $this->sudo->run(['mkdir', '-p', $targetDir]);
        $this->sudo->run(['mv', $stagedPath, $targetDir . '/' . $targetName]);
    }

    /**
     * Launch a background "lftp mirror" job to copy a whole remote folder
     * directly into this server (detached session, resumable, parallel).
     */
    public function startMirrorJob(
        RemoteFtpConnection $conn,
        string $remotePath,
        string $targetDir,
        ?string $explicitPassword = null
    ): string {
        $targetDir = $this->assertSafeLocalTarget($targetDir);
        $this->assertSafeHost($conn->host);
        $this->assertSafeUsername($conn->username);
        $this->assertSafeRemotePath($remotePath);

        $password = $explicitPassword ?? $conn->password;
        if ($password === null || $password === '') {
            throw new \RuntimeException('Se requiere la contraseña para conectar.');
        }

        $jobId = date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $jobBase = storage_path(self::JOB_ROOT);
        $jobDir = $jobBase . '/' . $jobId;
        @mkdir($jobDir, 0770, true);

        $meta = [
            'id'         => $jobId,
            'host'       => $conn->host,
            'username'   => $conn->username,
            'remote'     => $remotePath,
            'target'     => $targetDir,
            'status'     => 'running',
            'started_at' => now()->toDateTimeString(),
        ];
        @file_put_contents($jobDir . '/job.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $netrcPath = $jobDir . '/.netrc';
        $netrc = "machine {$conn->host} login {$conn->username} password " . str_replace("\n", '', $password) . "\n";
        @file_put_contents($netrcPath, $netrc);
        @chmod($netrcPath, 0600);

        $logPath = $jobDir . '/mirror.log';
        $scriptPath = $jobDir . '/run.sh';
        $script = $this->buildMirrorScript($conn, $remotePath, $targetDir, $jobDir, $logPath);
        @file_put_contents($scriptPath, $script);
        @chmod($scriptPath, 0700);

        // Detached session (setsid -f): returns immediately, keeps running after request ends.
        $this->sudo->run(['setsid', '-f', 'bash', $scriptPath], checkExit: false);

        Log::info('RemoteFtpService: mirror job started', [
            'job'    => $jobId,
            'host'   => $conn->host,
            'remote' => $remotePath,
            'target' => $targetDir,
        ]);

        return $jobId;
    }

    /**
     * List all job folders with basic status.
     */
    public function jobs(): array
    {
        $root = storage_path(self::JOB_ROOT);
        if (!is_dir($root)) {
            return [];
        }

        $jobs = [];
        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $dir = $root . '/' . $entry;
            if (!is_dir($dir)) {
                continue;
            }

            $meta = $this->readJobMeta($dir);

            $logPath = $dir . '/mirror.log';
            $logExists = is_file($logPath);
            $logMtime = $logExists ? (int) filemtime($logPath) : 0;
            $exit = $this->readExitCode($dir);
            $status = $this->jobStatus($meta, $exit, $dir);

            $total = $this->readTotalBytes($dir);
            $transferred = 0;
            $target = $meta['target'] ?? '';
            if ($target !== '' && is_dir($target)) {
                try {
                    $res = $this->sudo->run(['du', '-sb', $target], checkExit: false);
                    if ($res->successful()) {
                        $parts = preg_split('/\s+/', trim($res->stdout));
                        $transferred = isset($parts[0]) ? (int) $parts[0] : 0;
                    }
                } catch (\Throwable) {
                    // Best-effort only; progress stays unknown.
                }
            }

            $percent = null;
            if ($total > 0) {
                $percent = (int) round(min(100, $transferred / $total * 100));
            } elseif ($status === 'done') {
                $percent = 100;
            }

            $jobs[] = [
                'id'         => $entry,
                'host'       => $meta['host'] ?? '?',
                'remote'     => $meta['remote'] ?? '?',
                'target'     => $target,
                'status'     => $status,
                'exit'       => $exit,
                'started_at' => $meta['started_at'] ?? '',
                'log_mtime'  => $logMtime,
                'total_bytes'     => $total,
                'transferred_bytes' => $transferred,
                'percent'         => $percent,
            ];
        }

        usort($jobs, fn ($a, $b) => strcmp($b['id'], $a['id']));
        return $jobs;
    }

    public function tailJobLog(string $jobId, int $lines = 50): array
    {
        $jobId = basename($jobId);
        $logPath = storage_path(self::JOB_ROOT . '/' . $jobId . '/mirror.log');
        if (!is_file($logPath)) {
            return ['lines' => [], 'path' => $logPath];
        }

        $content = file($logPath, FILE_IGNORE_NEW_LINES);
        $content = is_array($content) ? $content : [];

        return [
            'lines' => array_slice($content, max(0, count($content) - $lines)),
            'path'  => $logPath,
        ];
    }

    public function deleteJob(string $jobId): void
    {
        $dir = storage_path(self::JOB_ROOT . '/' . basename($jobId));
        if (is_dir($dir)) {
            $this->sudo->run(['rm', '-rf', $dir], checkExit: false);
        }
    }

    // ─────────────────────────── helpers ───────────────────────────

    private function readJobMeta(string $jobDir): array
    {
        $json = $jobDir . '/job.json';
        return is_file($json) ? (json_decode((string) @file_get_contents($json), true) ?: []) : [];
    }

    private function buildMirrorScript(
        RemoteFtpConnection $conn,
        string $remotePath,
        string $targetDir,
        string $jobDir,
        string $logPath
    ): string {
        $settings = [
            'set net:timeout 15;',
            'set net:reconnect-interval-base 5;',
            'set xfer:use-temp-file yes;',
        ];

        if ($conn->protocol === 'ftps') {
            $settings[] = 'set ftp:ssl-force true;';
            $settings[] = 'set ftp:ssl-auth TLS;';
            $settings[] = 'set ftp:ssl-allow yes;';
            $settings[] = 'set ftp:ssl-protect-data yes;';
        } else {
            $settings[] = 'set ftp:ssl-allow false;';
        }

        $mirrorCmd = 'mirror --continue --parallel=3 --log=' . escapeshellarg($logPath) . ' ' . escapeshellarg($remotePath) . ' .';
        $cmd = 'lftp -p ' . (int) $conn->port
            . ' -e ' . escapeshellarg(implode(' ', $settings) . ' ' . $mirrorCmd . '; quit')
            . ' ' . escapeshellarg($conn->host);

        $statusPath = $jobDir . '/status';
        $totalPath = $jobDir . '/total';

        $duCmd = 'lftp -p ' . (int) $conn->port
            . ' -e ' . escapeshellarg(implode(' ', $settings) . ' du -b -s ' . escapeshellarg($remotePath) . '; quit')
            . ' ' . escapeshellarg($conn->host)
            . ' | awk \'{print $1}\' | head -1 > ' . escapeshellarg($totalPath) . "\n";

        // Redirected within the script so output survives after the PHP request ends.
        return "#!/bin/bash\n"
            . "export HOME=" . escapeshellarg($jobDir) . "\n"
            . "exec < /dev/null\n"
            . "exec > " . escapeshellarg($logPath) . " 2>&1\n"
            . "mkdir -p " . escapeshellarg($targetDir) . " || { echo \"EXIT_CODE=90\" > " . escapeshellarg($statusPath) . "; exit 0; }\n"
            . "cd " . escapeshellarg($targetDir) . " || { echo \"EXIT_CODE=90\" > " . escapeshellarg($statusPath) . "; exit 0; }\n"
            . "echo \"[LaraPanel] Calculando tamaño total de la carpeta remota...\"\n"
            . $duCmd
            . $cmd . "\n"
            . "echo \"[LaraPanel] El trabajo finalizó (código \"\$?\").\"\n"
            . "echo \"EXIT_CODE=\$?\" > " . escapeshellarg($statusPath) . "\n"
            . "exit 0\n";
    }

    private function jobStatus(array $meta, ?int $exit, string $jobDir): string
    {
        if ($exit !== null) {
            return $exit === 0 ? 'done' : 'failed';
        }

        if (isset($meta['status']) && $meta['status'] !== 'running') {
            return $meta['status'];
        }

        return $this->guessStatus($jobDir);
    }

    private function guessStatus(string $jobDir): string
    {
        $exit = $this->readExitCode($jobDir);
        if ($exit !== null) {
            return $exit === 0 ? 'done' : 'failed';
        }

        $logPath = $jobDir . '/mirror.log';
        if (is_file($logPath) && (int) filemtime($logPath) < time() - 60) {
            return 'stopped';
        }

        // Legacy jobs stuck before lftp ever ran (no log, no result).
        if (!is_file($logPath) && (int) filemtime($jobDir) < time() - 120) {
            return 'stopped';
        }

        return 'running';
    }

    private function readTotalBytes(string $jobDir): int
    {
        $raw = trim((string) @file_get_contents($jobDir . '/total'));
        return ($raw !== '' && is_numeric($raw)) ? (int) $raw : 0;
    }

    private function readExitCode(string $jobDir): ?int
    {
        $statusPath = $jobDir . '/status';
        if (!is_file($statusPath)) {
            return null;
        }
        if (preg_match('/EXIT_CODE=(\d+)/', (string) @file_get_contents($statusPath), $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function assertSafeHost(string $host): void
    {
        if (!preg_match('/^[a-zA-Z0-9\.:\-\[\]]+$/', $host)) {
            throw new \RuntimeException('Host inválido.');
        }
    }

    private function assertSafeUsername(string $username): void
    {
        if (!preg_match('/^[a-zA-Z0-9_\.\-@]+$/', $username)) {
            throw new \RuntimeException('Usuario inválido.');
        }
    }

    private function assertSafeRemotePath(string $path): void
    {
        if ($path === '' || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            throw new \RuntimeException('Ruta remota inválida.');
        }
    }

    private function assertSafeLocalTarget(string $path): string
    {
        $path = rtrim(trim($path), '/');
        if ($path === '' || !str_starts_with($path, '/')) {
            throw new \RuntimeException('El destino local debe ser una ruta absoluta.');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
            throw new \RuntimeException('Ruta local inválida.');
        }
        return $path;
    }

    private function assertSafeBaseName(string $name): void
    {
        if ($name === '' || $name === '.' || $name === '..' || str_contains($name, '/') || str_contains($name, "\0")) {
            throw new \RuntimeException('Nombre de archivo inválido.');
        }
    }
}