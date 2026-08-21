<div @if($autoRefresh) wire:poll.5s="refreshProcesses" @endif>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-microchip" style="color:var(--accent-light);margin-right:10px;"></i>
                Procesos del Sistema
            </h1>
            <p class="page-subtitle">Procesos en memoria y su consumo real de RAM/CPU.</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <label style="font-size:12px;color:var(--text-secondary);display:flex;align-items:center;gap:6px;cursor:pointer;">
                <input type="checkbox" wire:model.live="autoRefresh" style="accent-color:var(--accent);">
                Auto-actualizar
            </label>
            <button wire:click="refreshProcesses" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-rotate"></i> Actualizar
            </button>
        </div>
    </div>

    {{-- Alerts --}}
    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    {{-- Summary Cards --}}
    <div class="stats-row" style="margin-bottom:20px;">
        <div class="glass lp-panel" style="text-align:center;">
            <div style="font-size:24px;font-weight:800;color:var(--accent-light);">{{ count($processes) }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Procesos Totales</div>
        </div>
        <div class="glass lp-panel" style="text-align:center;">
            <div style="font-size:22px;font-weight:800;color:var(--success);">
                {{ \App\Services\MonitoringService::formatBytes($summary['ram']['used'] ?? 0) }}
            </div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">
                RAM Usada ({{ $summary['ram']['percent'] ?? 0 }}%)
            </div>
        </div>
        <div class="glass lp-panel" style="text-align:center;">
            <div style="font-size:22px;font-weight:800;color:{{ ($summary['ram']['swap_pct'] ?? 0) > 0 ? 'var(--warning)' : 'var(--text-muted)' }};">
                {{ \App\Services\MonitoringService::formatBytes($summary['ram']['swap_used'] ?? 0) }}
            </div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Swap en Uso</div>
        </div>
        <div class="glass lp-panel" style="text-align:center;">
            <div style="font-size:22px;font-weight:800;color:var(--text-primary);font-family:monospace;">
                {{ number_format($summary['load']['1m'] ?? 0, 2) }} / {{ number_format($summary['load']['5m'] ?? 0, 2) }}
            </div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Load Average (1m / 5m)</div>
        </div>
        <div class="glass lp-panel" style="text-align:center;">
            @php
                $running = collect($processes)->filter(fn($p) => str_contains($p['stat'], 'R') && !str_contains($p['stat'], 'S'))->count();
            @endphp
            <div style="font-size:24px;font-weight:800;color:var(--warning);">{{ $running }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">En Ejecución</div>
        </div>
    </div>

    {{-- Controls --}}
    <div class="filters-bar" style="flex-wrap:wrap;">
        <input type="text" wire:model.live.debounce.300ms="search" class="form-input"
               placeholder="Buscar por comando, usuario o PID..." style="max-width:320px;font-size:12px;">
        <button wire:click="$set('sortBy','mem')" class="btn {{ $sortBy === 'mem' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-memory"></i> Por RAM
        </button>
        <button wire:click="$set('sortBy','cpu')" class="btn {{ $sortBy === 'cpu' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-fire"></i> Por CPU
        </button>
        <span style="font-size:11px;color:var(--text-muted);margin-left:auto;">
            Mostrando {{ count($this->filteredProcesses) }} de {{ count($processes) }} procesos
        </span>
    </div>

    {{-- Process Table --}}
    <div class="glass lp-panel" style="padding:0;overflow:hidden;margin-bottom:24px;">
        <div class="table-responsive">
            <table class="lp-table">
                <thead>
                    <tr>
                        <th>PID</th>
                        <th>Usuario</th>
                        <th>CPU %</th>
                        <th>RAM</th>
                        <th>% Mem</th>
                        <th>Estado</th>
                        <th>Iniciado</th>
                        <th>Tiempo CPU</th>
                        <th>Comando</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $maxRss = max(1, collect($this->filteredProcesses)->max('rss_bytes'));
                    @endphp
                    @forelse($this->filteredProcesses as $p)
                        <tr>
                            <td style="font-family:monospace;color:var(--text-muted);">{{ $p['pid'] }}</td>
                            <td>{{ $p['user'] }}</td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div style="width:60px;height:5px;background:rgba(255,255,255,0.05);border-radius:3px;overflow:hidden;flex-shrink:0;">
                                        <div style="width:{{ min(100, $p['cpu']) }}%;height:100%;background:{{ $p['cpu'] > 80 ? 'var(--danger)' : ($p['cpu'] > 40 ? 'var(--warning)' : '#6366f1') }};"></div>
                                    </div>
                                    <span style="font-size:12px;min-width:38px;">{{ number_format($p['cpu'], 1) }}%</span>
                                </div>
                            </td>
                            <td style="min-width:130px;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div style="width:60px;height:5px;background:rgba(255,255,255,0.05);border-radius:3px;overflow:hidden;flex-shrink:0;">
                                        <div style="width:{{ round(($p['rss_bytes'] / $maxRss) * 100) }}%;height:100%;background:#27c93f;"></div>
                                    </div>
                                    <span style="font-size:12px;white-space:nowrap;" title="{{ $p['mem_pct'] }}% de la RAM total">
                                        {{ \App\Services\MonitoringService::formatBytes($p['rss_bytes']) }}
                                    </span>
                                </div>
                            </td>
                            <td style="font-size:12px;">{{ number_format($p['mem_pct'], 1) }}%</td>
                            <td>
                                @if(str_contains($p['stat'], 'Z'))
                                    <span class="badge badge-danger">Zombie</span>
                                @elseif(str_contains($p['stat'], 'R'))
                                    <span class="badge badge-accent">Ejecutando</span>
                                @elseif(str_contains($p['stat'], 'D'))
                                    <span class="badge badge-warning">Espera E/S</span>
                                @else
                                    <span class="badge badge-muted">{{ $p['stat'] }}</span>
                                @endif
                            </td>
                            <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">{{ $p['start'] }}</td>
                            <td style="font-size:12px;font-family:monospace;color:var(--text-muted);">{{ $p['cpu_time'] }}</td>
                            <td style="max-width:260px;">
                                <div style="font-size:13px;font-weight:600;">{{ $p['command'] }}</div>
                                <div style="font-size:10px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $p['full_cmd'] }}">{{ $p['full_cmd'] }}</div>
                            </td>
                            <td style="text-align:right;">
                                <button wire:click="confirmKill({{ $p['pid'] }}, '{{ addslashes($p['command']) }}')"
                                        class="btn btn-danger btn-sm" title="Terminar proceso"
                                        wire:loading.attr="disabled">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align:center;padding:32px;color:var(--text-muted);">
                                @if($errorMessage)
                                    No hay datos disponibles.
                                @else
                                    <i class="fa-solid fa-spinner fa-spin"></i> Cargando procesos...
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Kill Confirmation Modal --}}
    @if($confirmKillPid !== null)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;"
         wire:click="cancelKill">
        <div class="glass-elevated" style="max-width:440px;width:100%;padding:28px;border:1px solid rgba(239,68,68,0.35);" onclick="event.stopPropagation()">
            <h3 style="margin:0 0 12px;font-size:17px;display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-triangle-exclamation" style="color:var(--danger);"></i>
                Terminar proceso #{{ $confirmKillPid }}
            </h3>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;line-height:1.6;">
                Se enviará una señal al proceso
                @if($confirmKillCmd)<strong>"{{ $confirmKillCmd }}"</strong>@endif
                para finalizarlo. Si no responde, puedes forzarlo con SIGKILL.
                Esta acción queda registrada en el log de auditoría.
            </p>
            <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-secondary);cursor:pointer;margin-bottom:18px;">
                <input type="checkbox" wire:model="forceKill" style="accent-color:var(--danger);">
                Forzar con SIGKILL (-9) — no permite cerrar limpiamente
            </label>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button wire:click="cancelKill" class="btn btn-ghost">Cancelar</button>
                <button wire:click="killProcess" class="btn btn-danger" wire:loading.attr="disabled">
                    <span wire:loading.remove><i class="fa-solid fa-xmark"></i> {{ $forceKill ? 'Matar Proceso' : 'Terminar Proceso' }}</span>
                    <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Enviando señal...</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
