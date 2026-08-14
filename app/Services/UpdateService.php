<?php

namespace App\Services;

use App\Shell\ShellExecutor;
use Illuminate\Support\Facades\Cache;

class UpdateService
{
    public static function check(): array
    {
        $executor = new ShellExecutor();
        $baseDir = base_path();

        $branchResult = $executor->inDirectory($baseDir)->run(['git', 'branch', '--show-current'], false);
        $branch = trim($branchResult->stdout);
        if ($branch === '') {
            $branch = 'main';
        }

        $localResult = $executor->inDirectory($baseDir)->run(['git', 'rev-parse', 'HEAD'], false);
        if (! $localResult->successful()) {
            throw new \RuntimeException('No se pudo leer el commit local: ' . trim($localResult->stderr));
        }
        $localHash = trim($localResult->stdout);

        // ls-remote only reads the remote and does not require write access to .git.
        $remoteResult = $executor->inDirectory($baseDir)->run([
            'git', 'ls-remote', 'origin', "refs/heads/{$branch}",
        ], false);
        if (! $remoteResult->successful() || trim($remoteResult->stdout) === '') {
            throw new \RuntimeException('No se pudo consultar origin/' . $branch . ': ' . trim($remoteResult->stderr));
        }

        [$remoteHash] = preg_split('/\s+/', trim($remoteResult->stdout), 2);
        $pending = [];
        $latestMessage = 'Actualización remota disponible.';

        // Best effort: when permissions allow, refresh the local remote ref to show details.
        $fetchResult = $executor->inDirectory($baseDir)->run(['git', 'fetch', 'origin', $branch], false);
        if ($fetchResult->successful()) {
            $remoteRef = $executor->inDirectory($baseDir)->run(['git', 'rev-parse', "origin/{$branch}"], false);
            if ($remoteRef->successful()) {
                $remoteHash = trim($remoteRef->stdout);
            }

            $pendingResult = $executor->inDirectory($baseDir)->run([
                'git', 'log', "HEAD..origin/{$branch}", '--oneline', '--max-count=50',
            ], false);
            if ($pendingResult->successful()) {
                $pending = array_values(array_filter(explode("\n", trim($pendingResult->stdout))));
            }

            $messageResult = $executor->inDirectory($baseDir)->run([
                'git', 'log', '-1', "origin/{$branch}", '--pretty=%B',
            ], false);
            if ($messageResult->successful() && trim($messageResult->stdout) !== '') {
                $latestMessage = trim($messageResult->stdout);
            }
        }

        $statusResult = $executor->inDirectory($baseDir)->run(['git', 'status', '--porcelain'], false);

        return [
            'branch' => $branch,
            'current_hash' => $localHash,
            'current_message' => trim($executor->inDirectory($baseDir)->run(['git', 'log', '-1', '--pretty=%B'], false)->stdout),
            'latest_hash' => $remoteHash,
            'latest_message' => $latestMessage,
            'pending_commits' => $pending,
            'working_tree_dirty' => $statusResult->successful() && trim($statusResult->stdout) !== '',
            'checked_at' => now()->toDateTimeString(),
        ];
    }

    public static function isUpdateAvailableCached(): bool
    {
        return Cache::remember('larapanel_update_available', now()->addHours(4), function () {
            try {
                $result = static::check();
                return $result['current_hash'] !== $result['latest_hash'];
            } catch (\Throwable) {
                return false;
            }
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('larapanel_update_available');
    }
}
