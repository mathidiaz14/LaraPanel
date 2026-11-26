<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\GitDeployment;
use App\Models\GitDeploymentLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;

class GitService
{
    private const SAFE_ENV = [
        'HOME', 'PATH', 'SHELL', 'LANG', 'LC_ALL', 'TERM', 'TMPDIR', 'USER', 'LOGNAME', 'PWD',
    ];

    public function __construct()
    {
        $this->ensureSafeDirectory();
    }

    protected function ensureSafeDirectory(): void
    {
        try {
            $result = Process::run(['git', 'config', '--global', '--get-all', 'safe.directory']);
            if (!$result->output() || !str_contains($result->output(), '*')) {
                Process::run(['git', 'config', '--global', '--add', 'safe.directory', '*']);
            }
        } catch (\Throwable) {
        }
    }

    public function deploy(GitDeployment $deployment, string $triggeredBy = 'manual', ?string $commitHash = null): GitDeploymentLog
    {
        $requestedCommit = $this->normalizeCommit($commitHash);

        $branch = trim($deployment->branch ?: 'main');
        if (! preg_match('/^[A-Za-z0-9._\/-]{1,200}$/', $branch) || str_starts_with($branch, '-')) {
            throw new \InvalidArgumentException('La rama Git no es válida.');
        }

        $deployment->logs()
            ->where('status', 'running')
            ->update([
                'status' => 'failed',
                'output' => ">>> Marcado como fallido: el proceso anterior no finalizó.\n",
                'finished_at' => now(),
            ]);

        $log = $deployment->logs()->create([
            'status'          => 'running',
            'triggered_by'    => $triggeredBy,
            'commit_hash'     => $requestedCommit,
            'requested_commit'=> $requestedCommit,
        ]);

        if (!app()->isProduction()) {
            return $this->simulateDeploy($deployment, $log, $requestedCommit);
        }

        $domainPath = $deployment->deploy_path ?: rtrim(config('larapanel.paths.webroots', '/var/www'), '/') . '/' . $deployment->domain_name . '/public_html';
        $outputBuffer = ">>> Starting deployment for {$deployment->domain_name}\n";
        $outputBuffer .= ">>> Repository: {$deployment->repository_url} | Branch: {$deployment->branch}\n";
        $outputBuffer .= $requestedCommit ? ">>> Requested commit: {$requestedCommit}\n\n" : "\n";

        $executor = new \App\Shell\SudoExecutor();
        if (!is_dir($domainPath)) {
            $outputBuffer .= ">>> Creating directory {$domainPath}...\n";
            $executor->run(['mkdir', '-p', $domainPath], false);
        }
        $executor->run(['chown', '-R', config('larapanel.server.sudo_user', 'www-data') . ':' . config('larapanel.server.sudo_user', 'www-data'), $domainPath], false);

        $isRepo = Process::path($domainPath)->run(['git', 'rev-parse', '--is-inside-work-tree']);

        try {
            if ($isRepo->successful()) {
                $outputBuffer .= ">>> Repository found. Pulling latest changes...\n";
                $fetch = Process::path($domainPath)
                    ->timeout(120)
                    ->run(['git', 'fetch', 'origin', $branch]);
                $result = $fetch->successful()
                    ? Process::path($domainPath)->timeout(120)->run(['git', 'reset', '--hard', "origin/{$branch}"])
                    : $fetch;
                $outputBuffer .= $result->output() . $result->errorOutput() . "\n";
                if (!$result->successful()) throw new \Exception('Git pull failed.');

                $this->checkoutCommit($domainPath, $requestedCommit, $outputBuffer);
            } else {
                $outputBuffer .= ">>> Directory is not a repository. Cloning...\n";
                $tmpDir = '/tmp/git_clone_' . bin2hex(random_bytes(12));
                $result = null;
                try {
                    $result = Process::timeout(300)->run([
                        'git', 'clone', '--branch', $branch, '--', $deployment->repository_url, $tmpDir,
                    ]);
                    if ($result->successful()) {
                        $result = Process::path($domainPath)->run(['mv', '-T', $tmpDir.'/.git', $domainPath.'/.git']);
                    }
                    if ($result->successful()) {
                        $result = Process::path($domainPath)->run(['git', 'reset', '--hard']);
                    }
                    if ($result->successful()) {
                        $this->checkoutCommit($domainPath, $requestedCommit, $outputBuffer);
                    }
                } finally {
                    if (is_dir($tmpDir)) {
                        Process::run(['rm', '-rf', $tmpDir]);
                    }
                }
                $outputBuffer .= $result?->output() . $result?->errorOutput() . "\n";
                if (!$result?->successful()) throw new \Exception('Git clone failed.');
            }

            if (!empty($deployment->deploy_script)) {
                $outputBuffer .= "\n>>> Executing custom deployment script...\n";
                $this->runDeployScript($domainPath, $deployment->deploy_script, $outputBuffer);
            }

            $outputBuffer .= "\n>>> Deployment completed successfully.\n";
            $status = 'success';

            $commitInfo = Process::path($domainPath)->run(['git', 'log', '-1', '--pretty=format:%H|%s']);
            if ($commitInfo->successful()) {
                $parts = explode('|', $commitInfo->output(), 2);
                $log->commit_hash = $parts[0] ?? $requestedCommit;
                $log->commit_message = $parts[1] ?? null;
                $log->save();
            }
        } catch (\Throwable $e) {
            $outputBuffer .= "\n>>> ERROR: " . $e->getMessage() . "\n";
            $status = 'failed';
        }

        return $this->finalize($deployment, $log, $status, $outputBuffer);
    }

    protected function runDeployScript(string $domainPath, string $script, string &$outputBuffer): void
    {
        $scriptPath = sys_get_temp_dir() . '/lp_git_deploy_' . bin2hex(random_bytes(8)) . '.sh';
        $cleanScript = $this->cleanEnvironment() . "\n" . $script;

        try {
            file_put_contents($scriptPath, $cleanScript);
            chmod($scriptPath, 0700);

            $result = Process::path($domainPath)
                ->timeout(300)
                ->run(['/bin/bash', $scriptPath]);

            $outputBuffer .= $result->output() . $result->errorOutput() . "\n";
        } finally {
            @unlink($scriptPath);
        }

        if (! $result->successful()) {
            throw new \Exception('Deploy script failed.');
        }
    }

    protected function cleanEnvironment(): string
    {
        $variables = [];

        foreach (array_keys(getenv() ?: []) as $name) {
            if (in_array($name, self::SAFE_ENV, true) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                continue;
            }
            $variables[] = $name;
        }

        if ($variables === []) {
            return "# Entorno heredado limpio\n";
        }

        return "# Entorno heredado limpio\nunset " . implode(' ', $variables) . "\n";
    }

    protected function checkoutCommit(string $domainPath, ?string $commit, string &$outputBuffer): void
    {
        if (! $commit) {
            return;
        }

        $exists = Process::path($domainPath)->run(['git', 'cat-file', '-e', $commit . '^{commit}']);

        if (! $exists->successful()) {
            throw new \Exception("El commit solicitado {$commit} no existe en el repositorio local.");
        }

        $result = Process::path($domainPath)->timeout(120)->run(['git', 'reset', '--hard', $commit]);
        $outputBuffer .= ">>> Checkout commit {$commit}\n";
        $outputBuffer .= $result->output() . $result->errorOutput() . "\n";

        if (! $result->successful()) {
            throw new \Exception('No se pudo hacer checkout del commit solicitado.');
        }
    }

    protected function normalizeCommit(?string $commitHash): ?string
    {
        if ($commitHash === null || trim($commitHash) === '') {
            return null;
        }

        $commit = strtolower(trim($commitHash));

        if (! preg_match('/^[0-9a-f]{7,64}$/', $commit)) {
            throw new \InvalidArgumentException('El hash del commit no es válido.');
        }

        return $commit;
    }

    protected function finalize(
        GitDeployment $deployment,
        GitDeploymentLog $log,
        string $status,
        string $output,
    ): GitDeploymentLog {
        $finishedAt = now();
        $durationMs = (int) ($log->created_at instanceof Carbon
            ? $log->created_at->diffInMilliseconds($finishedAt)
            : 0);

        $log->update([
            'status' => $status,
            'output' => $output,
            'finished_at' => $finishedAt,
            'duration_ms' => $durationMs,
        ]);

        if ($status === 'success') {
            $deployment->update(['last_deployed_at' => $finishedAt]);
        }

        GitDeployDispatcher::forget($deployment->id);

        AuditLog::record('git.deploy.finished', $deployment->domain_name, [
            'deployment_id' => $deployment->id,
            'log_id' => $log->id,
            'status' => $status,
            'duration_ms' => $durationMs,
            'triggered_by' => $log->triggered_by,
        ], severity: $status === 'success' ? 'info' : 'error');

        return $log;
    }

    protected function simulateDeploy(GitDeployment $deployment, GitDeploymentLog $log, ?string $commitHash = null): GitDeploymentLog
    {
        sleep(3);

        $output = ">>> Starting simulated deployment for {$deployment->domain_name}\n";
        $output .= ">>> Repository: {$deployment->repository_url} | Branch: {$deployment->branch}\n";
        $output .= $commitHash ? ">>> Requested commit: {$commitHash}\n\n" : "\n";
        $output .= "git fetch origin {$deployment->branch}\n";
        $output .= "From https://github.com/larapanel/simulated-repo\n";
        $output .= " * branch            main       -> FETCH_HEAD\n";
        $output .= "git reset --hard origin/{$deployment->branch}\n";
        $output .= "HEAD is now at " . substr(md5((string) rand()), 0, 7) . " Simulated commit message\n";

        if (!empty($deployment->deploy_script)) {
            $output .= "\n>>> Executing custom deployment script...\n";
            $output .= "composer install --no-interaction --prefer-dist --optimize-autoloader\n";
            $output .= "Generating optimized autoload files\n";
            $output .= "> Illuminate\Foundation\ComposerScripts::postAutoloadDump\n";
            $output .= "php artisan migrate --force\n";
            $output .= "Nothing to migrate.\n";
            $output .= "npm run build\n";
            $output .= "> build\n> vite build\n\n✓ 34 modules transformed.\n";
        }

        $output .= "\n>>> Deployment completed successfully.\n";

        $log->commit_hash = $commitHash ?: substr(md5((string) rand()), 0, 40);
        $log->commit_message = 'Simulated automatic commit from web editor';
        $log->save();

        return $this->finalize($deployment, $log, 'success', $output);
    }

    public function getRepoStatus(GitDeployment $deployment): array
    {
        $domainPath = $deployment->deploy_path ?: rtrim(config('larapanel.paths.webroots', '/var/www'), '/') . '/' . $deployment->domain_name . '/public_html';

        if (!is_dir($domainPath)) {
            return ['status' => 'not_found', 'message' => 'El directorio no existe en el servidor.'];
        }

        $isRepo = Process::path($domainPath)->run('git rev-parse --is-inside-work-tree');
        if (!$isRepo->successful()) {
            return ['status' => 'not_initialized', 'message' => 'El directorio existe pero no es un repositorio de git. Se clonará en el próximo despliegue.'];
        }

        $branch = Process::path($domainPath)->run('git rev-parse --abbrev-ref HEAD')->output();
        $commit = Process::path($domainPath)->run('git log -1 --pretty=format:"%h - %s (%cr)"')->output();
        $status = Process::path($domainPath)->run('git status --short')->output();

        return [
            'status' => 'active',
            'branch' => trim($branch),
            'commit' => trim($commit),
            'changes' => trim($status) !== '' ? trim($status) : 'Directorio de trabajo limpio',
            'has_changes' => trim($status) !== '',
        ];
    }
}
