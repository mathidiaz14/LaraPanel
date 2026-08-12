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

        return view('livewire.terminal.terminal-index', [
            'remoteServers' => $remoteServers,
            'reverb' => [
                'key' => config('reverb.apps.apps.0.key'),
                'host' => config('reverb.apps.apps.0.options.host') ?: request()->getHost(),
                'port' => (int) config('reverb.apps.apps.0.options.port', 443),
                'scheme' => config('reverb.apps.apps.0.options.scheme', 'https'),
            ],
        ])->layout('layouts.app', [
            'title' => 'Terminal Web',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Terminal</strong>',
        ]);
    }
}
