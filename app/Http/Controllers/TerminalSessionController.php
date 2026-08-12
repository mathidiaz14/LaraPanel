<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Server;
use App\Models\TerminalSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TerminalSessionController extends Controller
{
    /**
     * Create a new interactive terminal session.
     * POST /terminal/session
     */
    public function store(Request $request): JsonResponse
    {
        if (! config('larapanel.security.terminal.enabled', true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'La terminal interactiva está deshabilitada.',
            ], 403);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(['local', 'ssh'])],
            'server_id' => ['required_if:type,ssh', 'nullable', 'integer'],
            'cwd' => ['nullable', 'string', 'max:255'],
        ]);

        $type = $validated['type'];
        $serverId = null;

        if ($type === 'ssh') {
            $server = Server::find($validated['server_id'] ?? null);

            if (! $server || $server->is_local || ! $server->is_active || $server->user_id !== $request->user()->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Servidor remoto no válido o inaccesible.',
                ], 422);
            }

            $serverId = $server->id;
        }

        $max = (int) config('larapanel.security.terminal.max_concurrent_sessions', 3);
        $active = TerminalSession::openForUser($request->user()->id)->count();

        if ($active >= $max) {
            return response()->json([
                'status' => 'error',
                'message' => "Límite de {$max} sesiones de terminal abiertas alcanzado.",
            ], 429);
        }

        $cwd = $validated['cwd'] ?? config('larapanel.security.terminal.default_cwd', '/var/www');

        $session = TerminalSession::createForUser($request->user()->id, $type, $serverId, $cwd);

        AuditLog::record('terminal.session.start', "terminal:{$type}", [
            'session_id' => $session->id,
            'channel' => $session->channel,
            'server_id' => $serverId,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'session_id' => $session->id,
                'token' => $session->token,
                'channel' => $session->channelName(),
                'type' => $session->type,
                'server' => $type === 'ssh' ? [
                    'id' => $server->id,
                    'name' => $server->name,
                    'hostname' => $server->hostname,
                    'username' => $server->username,
                ] : null,
            ],
        ], 201);
    }

    /**
     * Close (kill) an interactive terminal session.
     * DELETE /terminal/session/{session}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $session = TerminalSession::openForUser($request->user()->id)
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $session) {
            abort(404);
        }

        $session->close();

        AuditLog::record('terminal.session.close', "terminal:{$session->type}", [
            'session_id' => $session->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Sesión de terminal cerrada.',
        ]);
    }
}
