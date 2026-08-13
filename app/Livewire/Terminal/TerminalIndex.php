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

        $configuredHost = config('reverb.apps.apps.0.options.host');
        $reverbHost = (empty($configuredHost) || in_array($configuredHost, ['localhost', '127.0.0.1'], true))
            ? request()->getHost()
            : $configuredHost;

        $configuredPort = (int) config('reverb.apps.apps.0.options.port', 443);
        // Si el puerto configurado es el interno de Reverb (8080/8081), usar el puerto público Nginx (443/80 o request()->getPort())
        if (in_array($configuredPort, [8080, 8081, 0], true)) {
            $reverbPort = (int) (request()->getPort() ?: (request()->secure() ? 443 : 80));
        } else {
            $reverbPort = $configuredPort;
        }

        $reverbScheme = config('reverb.apps.apps.0.options.scheme', request()->secure() ? 'https' : 'http');

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
