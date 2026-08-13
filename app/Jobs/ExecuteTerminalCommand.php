<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\TerminalCommandHistory;
use App\Services\TerminalCommandPolicy;
use App\Services\TerminalService;
use App\Shell\RemoteShellExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteTerminalCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(public readonly int $historyId) {}

    public function handle(TerminalCommandPolicy $policy, TerminalService $terminal): void
    {
        $history = TerminalCommandHistory::find($this->historyId);
        if (! $history || $history->cancel_requested) {
            $history?->update(['status' => 'cancelled', 'finished_at' => now()]);
            return;
        }

        $started = microtime(true);
        $history->update(['status' => 'running', 'started_at' => now()]);

        try {
            $command = $policy->validate($history->command);
            $server = $history->server_id ? Server::find($history->server_id) : null;

            if ($server && ! $server->is_local) {
                $result = (new RemoteShellExecutor($server))
                    ->withTimeout(300)
                    ->inDirectory($history->cwd)
                    ->run($policy->tokens($command), false);
                $output = $result->stdout . $result->stderr;
                $exitCode = $result->exitCode;
            } else {
                $result = $terminal->execute($command, $history->cwd);
                $output = $result['output'];
                $exitCode = $result['code'];
            }

            $status = $history->refresh()->cancel_requested ? 'cancelled' : ($exitCode === 0 ? 'success' : 'failed');
            $history->update([
                'status' => $status,
                'output' => $output,
                'exit_code' => $exitCode,
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
            AuditLog::record('terminal.command.complete', $command, [
                'history_id' => $history->id,
                'server_id' => $history->server_id,
                'exit_code' => $exitCode,
                'status' => $status,
            ], $exitCode === 0 ? 'info' : 'warning', $history->user_id);
        } catch (\Throwable $e) {
            Log::error('Terminal command job failed', ['history_id' => $history->id, 'error' => $e->getMessage()]);
            $history->update([
                'status' => 'failed',
                'output' => $e->getMessage(),
                'exit_code' => 1,
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        }
    }
}
