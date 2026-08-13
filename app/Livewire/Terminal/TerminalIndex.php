<?php

namespace App\Livewire\Terminal;

use App\Models\Server;
use Livewire\Component;

class TerminalIndex extends Component
{
    public function render()
    {
        $remoteServers = Server::query()
            ->forUser(auth()->id())
            ->where('is_local', false)
            ->orderBy('name')
            ->get(['id', 'name', 'hostname', 'username', 'port', 'status']);

        $configuredHost = env('REVERB_HOST') ?: config('reverb.apps.apps.0.options.host');
        $reverbHost = (empty($configuredHost) || in_array($configuredHost, ['localhost', '127.0.0.1'], true))
            ? request()->getHost()
            : $configuredHost;

        $reverbPort = (int) (env('REVERB_PORT') ?: (config('reverb.apps.apps.0.options.port') ?: 8081));
        $reverbScheme = env('REVERB_SCHEME') ?: (config('reverb.apps.apps.0.options.scheme') ?: (request()->secure() ? 'https' : 'http'));

        return view('livewire.terminal.terminal-index', [
            'remoteServers' => $remoteServers,
            'reverb' => [
                'key' => config('reverb.apps.apps.0.key'),
                'host' => $reverbHost,
                'port' => $reverbPort,
                'scheme' => $reverbScheme,
            ],
        ])->layout('layouts.app', [
            'title' => 'Terminal Web',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Terminal</strong>',
        ]);
    }
}
