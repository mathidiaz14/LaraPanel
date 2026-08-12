<?php

namespace App\Listeners;

use App\Models\TerminalSession;
use App\Services\TerminalSessionManager;
use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Events\MessageReceived;
use Throwable;

/**
 * HandleTerminalMessage — consumes Reverb client events for the private
 * terminal channels (`client-terminal-*`) and forwards them to the PTY.
 *
 * Channel membership was already enforced by Reverb when the client event was
 * accepted (private channel + canJoin()), so the token is re-validated here as
 * a second factor on attach and the session is re-checked on every message.
 */
class HandleTerminalMessage
{
    public function __construct(
        protected TerminalSessionManager $manager,
    ) {}

    public function handle(MessageReceived $event): void
    {
        try {
            $this->process($event);
        } catch (Throwable $e) {
            $this->deny($event->connection, 'Error procesando el mensaje: '.$e->getMessage());
        }
    }

    protected function process(MessageReceived $event): void
    {
        $parsed = $this->parseMessage($event->message);

        if ($parsed === null) {
            return;
        }

        $uuid = $parsed['uuid'];

        $session = TerminalSession::where('channel', $uuid)->first();

        if (! $session || ! $session->isActive()) {
            $this->deny($event->connection, 'Sesión de terminal no válida.');

            return;
        }

        switch ($parsed['name']) {
            case 'client-terminal-attach':
                $this->attach($event->connection, $session, $parsed['data']);
                break;

            case 'client-terminal-data':
                $this->data($parsed['data'], $session->id);
                break;

            case 'client-terminal-resize':
                $this->resize($parsed['data'], $session->id);
                break;

            case 'client-terminal-kill':
                $this->manager->kill($session->id);
                break;
        }
    }

    /**
     * Decode and route a raw socket message. Returns null when the message is
     * not a terminal client event or its shape is invalid.
     *
     * @return array{name: string, uuid: string, data: array|string|null}|null
     */
    public function parseMessage(string $message): ?array
    {
        $decoded = json_decode($message, true);

        if (! is_array($decoded)) {
            return null;
        }

        $name = is_string($decoded['event'] ?? null) ? $decoded['event'] : '';

        if (! str_starts_with($name, 'client-terminal-')) {
            return null;
        }

        $channel = is_string($decoded['channel'] ?? null) ? $decoded['channel'] : '';

        if (! str_starts_with($channel, 'private-terminal.')) {
            return null;
        }

        return [
            'name' => $name,
            'uuid' => Str::after($channel, 'private-terminal.'),
            'data' => is_array($decoded['data'] ?? null) ? $decoded['data'] : null,
        ];
    }

    protected function attach(Connection $connection, TerminalSession $session, mixed $payload): void
    {
        $token = is_string($payload['token'] ?? null) ? $payload['token'] : null;

        if ($token === null || ! hash_equals($session->token, $token)) {
            $this->deny($connection, 'Token de sesión inválido.');

            return;
        }

        $this->manager->attach($session, $connection->app());
    }

    protected function data(mixed $payload, int $sessionId): void
    {
        $b64 = is_string($payload['b64'] ?? null) ? $payload['b64'] : null;

        if ($b64 === null) {
            return;
        }

        $bytes = base64_decode($b64, true);

        if ($bytes === false || $bytes === '') {
            return;
        }

        $this->manager->write($sessionId, $bytes);
    }

    protected function resize(mixed $payload, int $sessionId): void
    {
        $cols = (int) ($payload['cols'] ?? 0);
        $rows = (int) ($payload['rows'] ?? 0);

        if ($cols > 0 && $rows > 0) {
            $this->manager->resize($sessionId, $cols, $rows);
        }
    }

    protected function deny(Connection $connection, string $message): void
    {
        try {
            $connection->send(json_encode([
                'event' => 'pusher:error',
                'data' => json_encode([
                    'code' => 4301,
                    'message' => $message,
                ]),
            ]));
        } catch (Throwable) {
            // connection may already be gone
        }
    }
}
