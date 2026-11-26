<?php

namespace App\Services;

use App\Models\GitDeployment;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class GitDeployDispatcher
{
    public const RUNNING_TTL_MINUTES = 15;

    public static function runningKey(int $deploymentId): string
    {
        return "git_deploy_running_{$deploymentId}";
    }

    public function isRunning(int $deploymentId): bool
    {
        $deployment = GitDeployment::find($deploymentId);

        if (! $deployment) {
            return false;
        }

        return Cache::has(self::runningKey($deploymentId))
            || $deployment->logs()
                ->whereIn('status', ['pending', 'running'])
                ->where('created_at', '>=', now()->subMinutes(self::RUNNING_TTL_MINUTES))
                ->exists();
    }

    public function dispatch(int $deploymentId, string $trigger = 'manual', ?string $commitHash = null): void
    {
        if ($commitHash !== null && ! preg_match('/^[0-9a-f]{7,64}$/i', $commitHash)) {
            throw new \InvalidArgumentException('El hash del commit no es válido.');
        }

        $php = (new PhpExecutableFinder())->find() ?: 'php';
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/git-deploy.log');
        $runUser = config('larapanel.server.sudo_user', 'www-data');
        $useSudo = PHP_OS_FAMILY === 'Linux' && is_file('/usr/bin/sudo');

        $command = sprintf(
            ($useSudo ? 'sudo -n -u %s ' : '') . 'setsid nohup %s %s git:deploy %d --trigger=%s %s >> %s 2>&1 &',
            escapeshellarg($runUser),
            escapeshellarg($php),
            escapeshellarg($artisan),
            $deploymentId,
            escapeshellarg($trigger),
            $commitHash ? '--commit=' . escapeshellarg($commitHash) : '',
            escapeshellarg($logFile),
        );

        Cache::put(
            self::runningKey($deploymentId),
            now()->timestamp,
            now()->addMinutes(self::RUNNING_TTL_MINUTES),
        );

        $result = Process::fromShellCommandline($command)->setTimeout(5)->run();

        if ($result !== 0) {
            self::forget($deploymentId);
            throw new \RuntimeException('No se pudo lanzar el despliegue en segundo plano.');
        }
    }

    public static function forget(int $deploymentId): void
    {
        Cache::forget(self::runningKey($deploymentId));
    }
}
