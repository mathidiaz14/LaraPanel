<div wire:poll.10s="loadData">
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-network-wired" style="color:var(--accent-light);margin-right:10px;"></i>
                Conexiones de Red
            </h1>
            <p class="page-subtitle">Puertos en escucha y conexiones activas del servidor.</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <span style="font-size:11px;color:var(--text-muted);">Auto: 10s</span>
            <button wire:click="loadData" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-rotate"></i> Actualizar
            </button>
        </div>
    </div>

    {{-- Alerts --}}
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    {{-- Summary Cards --}}
    <div class="stats-row" style="margin-bottom:20px;">
        <div class="glass lp-panel" style="text-align:center;">
            <div style="font-size:24px;font-weight:800;color:var(--accent-light);">{{ $summary['listening'] }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Puertos en Escucha</div>
        </div>
        <div class="glass lp-panel" style="text-align:center;">
            <div style="font-size:24px;font-weight:800;color:var(--success);">{{ $summary['established'] }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Conexiones Establecidas</div>
        </div>
        <div class="glass lp-panel" style="text-align:center;">
            <div style="font-size:24px;font-weight:800;color:var(--warning);">{{ $summary['unique_ips'] }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">IPs Remotas Únicas</div>
        </div>
    </div>

    {{-- Tabs --}}
    <div style="display:flex;gap:6px;margin-bottom:20px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;">
        <button wire:click="$set('activeTab','ports')" class="btn {{ $activeTab === 'ports' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-plug"></i> Puertos ({{ count($ports) }})
        </button>
        <button wire:click="$set('activeTab','connections')" class="btn {{ $activeTab === 'connections' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-right-left"></i> Conexiones ({{ count($connections) }})
        </button>
        <input type="text" wire:model.live.debounce.300ms="search" class="form-input"
               placeholder="Filtrar por puerto, IP o proceso..." style="max-width:280px;margin-left:auto;font-size:12px;">
    </div>

    {{-- TAB: Listening Ports --}}
    @if($activeTab === 'ports')
    <div class="glass lp-panel" style="padding:0;overflow:hidden;margin-bottom:24px;">
        <div class="table-responsive">
            <table class="lp-table">
                <thead>
                    <tr>
                        <th>Protocolo</th>
                        <th>Dirección Local</th>
                        <th>Puerto</th>
                        <th>Proceso</th>
                        <th>PID</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->filteredPorts as $r)
                        <tr>
                            <td><span class="badge {{ $r['proto'] === 'tcp' ? 'badge-accent' : 'badge-warning' }}">{{ strtoupper($r['proto']) }}</span></td>
                            <td style="font-family:monospace;font-size:12px;">{{ $r['local_addr'] }}</td>
                            <td><strong>{{ $r['local_port'] }}</strong></td>
                            <td style="font-weight:600;">
                                {{ $r['process'] ?? '—' }}
                            </td>
                            <td style="font-family:monospace;color:var(--text-muted);">{{ $r['pid'] ?? '—' }}</td>
                            <td>{{ $r['user'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted);">
                            {{ $search ? 'Sin resultados para la búsqueda.' : 'Sin puertos en escucha.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- TAB: Established --}}
    @else
    <div class="glass lp-panel" style="padding:0;overflow:hidden;margin-bottom:24px;">
        <div class="table-responsive">
            <table class="lp-table">
                <thead>
                    <tr>
                        <th>Protocolo</th>
                        <th>Local</th>
                        <th>Peer Remoto</th>
                        <th>Proceso</th>
                        <th>Usuario</th>
                        <th>Recv-Q / Send-Q</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->filteredConnections as $c)
                        <tr>
                            <td><span class="badge badge-accent">{{ strtoupper($c['proto']) }}</span></td>
                            <td style="font-family:monospace;font-size:12px;">{{ $c['local_addr'] }}:{{ $c['local_port'] }}</td>
                            <td style="font-family:monospace;font-size:12px;color:var(--warning);">{{ $c['peer'] }}</td>
                            <td style="font-weight:600;">{{ $c['process'] ?? '—' }}</td>
                            <td>{{ $c['user'] ?? '—' }}</td>
                            <td style="font-family:monospace;font-size:12px;color:var(--text-muted);">{{ $c['recv_q'] }} / {{ $c['send_q'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted);">
                            {{ $search ? 'Sin resultados para la búsqueda.' : 'Sin conexiones establecidas.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
