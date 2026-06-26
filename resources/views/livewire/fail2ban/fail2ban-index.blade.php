<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-ban" style="color:{{ $isRunning ? 'var(--danger)' : 'var(--text-muted)' }};margin-right:10px;"></i>
                Fail2ban
            </h1>
            <p class="page-subtitle">Protección contra ataques de fuerza bruta. Bloqueo automático de IPs sospechosas.</p>
        </div>
        <div style="display:flex;gap:8px;">
            <button wire:click="reloadConfig" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-rotate"></i> Recargar Config
            </button>
            <button wire:click="restartFail2ban" class="btn btn-ghost btn-sm" style="color:var(--warning);" onclick="return confirm('¿Reiniciar fail2ban? El servicio no estará disponible brevemente.')">
                <i class="fa-solid fa-power-off"></i> Reiniciar
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

    {{-- Status Cards --}}
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px;">
        {{-- Running state --}}
        <div class="glass lp-panel" style="text-align:center;border-color:{{ $isRunning ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)' }};">
            <i class="fa-solid fa-{{ $isRunning ? 'circle-check' : 'circle-xmark' }}" style="font-size:22px;color:{{ $isRunning ? 'var(--success)' : 'var(--danger)' }};margin-bottom:8px;display:block;"></i>
            <div style="font-size:12px;font-weight:700;color:{{ $isRunning ? 'var(--success)' : 'var(--danger)' }};">{{ $isRunning ? 'ACTIVO' : 'DETENIDO' }}</div>
            <div style="font-size:10px;color:var(--text-muted);">Servicio</div>
        </div>
        {{-- Active bans --}}
        <div class="glass lp-panel" style="text-align:center;">
            <div style="font-size:26px;font-weight:800;color:var(--danger);">{{ $globalStats['total_active_bans'] }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Baneadas Ahora</div>
        </div>
        {{-- Today's bans --}}
        <div class="glass lp-panel" style="text-align:center;">
            <div style="font-size:26px;font-weight:800;color:var(--warning);">{{ $globalStats['total_bans_today'] }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Bans Hoy</div>
        </div>
        {{-- Active jails --}}
        <div class="glass lp-panel" style="text-align:center;">
            <div style="font-size:26px;font-weight:800;color:var(--accent-light);">{{ $globalStats['jails_active'] }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Jails Activos</div>
        </div>
        {{-- Most attacked --}}
        <div class="glass lp-panel" style="text-align:center;">
            <div style="font-size:14px;font-weight:700;color:var(--text-primary);font-family:monospace;">{{ $globalStats['most_attacked_jail'] }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:4px;">Más Atacado</div>
        </div>
    </div>

    {{-- Tabs --}}
    <div style="display:flex;gap:6px;margin-bottom:20px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;">
        <button wire:click="$set('activeTab','dashboard')" class="btn {{ $activeTab === 'dashboard' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </button>
        <button wire:click="$set('activeTab','jails')" class="btn {{ $activeTab === 'jails' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-bars-staggered"></i> Jails ({{ count($status['jails']) }})
        </button>
        <button wire:click="$set('activeTab','log')" class="btn {{ $activeTab === 'log' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-file-lines"></i> Log en Vivo
        </button>
        <button wire:click="$set('activeTab','history')" class="btn {{ $activeTab === 'history' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-clock-rotate-left"></i> Historial
        </button>
    </div>

    {{-- TAB: Dashboard --}}
    @if($activeTab === 'dashboard')
    <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:16px;align-items:start;">

        {{-- Manual Ban Form --}}
        <div class="glass lp-panel">
            <h2 class="panel-title" style="margin-bottom:16px;">
                <i class="fa-solid fa-ban" style="color:var(--danger);margin-right:8px;"></i>
                Banear IP Manualmente
            </h2>

            <div class="form-group">
                <label class="form-label" style="font-size:11px;">Dirección IP</label>
                <input type="text" wire:model="banIp" class="form-input" placeholder="185.220.101.45" style="font-family:monospace;">
                @error('banIp') <div style="font-size:10px;color:var(--danger);margin-top:3px;">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label class="form-label" style="font-size:11px;">Jail</label>
                <select wire:model="banJail" class="form-input" style="font-size:12px;">
                    @foreach($status['jails'] as $j)
                    <option value="{{ $j }}">{{ $j }}</option>
                    @endforeach
                </select>
                @error('banJail') <div style="font-size:10px;color:var(--danger);margin-top:3px;">{{ $message }}</div> @enderror
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label" style="font-size:11px;">Motivo <span style="color:var(--text-muted);">— opcional</span></label>
                <input type="text" wire:model="banReason" class="form-input" placeholder="Ej: Escaneo de puertos detectado" style="font-size:12px;">
            </div>

            <button wire:click="banIp" class="btn btn-danger" style="width:100%;justify-content:center;" wire:loading.attr="disabled">
                <span wire:loading.remove><i class="fa-solid fa-ban"></i> Banear IP</span>
                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Baneando...</span>
            </button>

            <div style="margin-top:12px;font-size:11px;color:var(--text-muted);text-align:center;">
                <i class="fa-solid fa-circle-info" style="margin-right:4px;"></i>
                El ban dura según el parámetro <code>bantime</code> de cada jail.
            </div>
        </div>

        {{-- Top Banned IPs --}}
        <div class="glass lp-panel">
            <h2 class="panel-title" style="margin-bottom:14px;">
                <i class="fa-solid fa-fire" style="color:var(--danger);margin-right:8px;"></i>
                IPs Más Atacantes
            </h2>
            @if(!empty($globalStats['top_banned_ips']))
            <div style="display:flex;flex-direction:column;gap:8px;">
                @foreach($globalStats['top_banned_ips'] as $i => $entry)
                @php $pct = round(($entry['count'] / ($globalStats['top_banned_ips'][0]['count'] ?? 1)) * 100); @endphp
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="font-size:11px;color:var(--text-muted);width:16px;text-align:right;">{{ $i+1 }}</div>
                    <div style="flex:1;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:3px;">
                            <code style="font-size:12px;color:var(--text-primary);">{{ $entry['ip'] }}</code>
                            <div style="display:flex;align-items:center;gap:8px;">
                                @if(isset($entry['country']))
                                <span style="font-size:10px;background:rgba(255,255,255,0.06);border-radius:4px;padding:1px 6px;color:var(--text-muted);">{{ $entry['country'] }}</span>
                                @endif
                                <span style="font-size:11px;color:var(--danger);font-weight:700;">{{ number_format($entry['count']) }} intentos</span>
                            </div>
                        </div>
                        <div style="height:4px;border-radius:2px;background:rgba(255,255,255,0.06);overflow:hidden;">
                            <div style="height:100%;width:{{ $pct }}%;background:var(--danger);border-radius:2px;opacity:{{ 1 - ($i * 0.15) }};"></div>
                        </div>
                    </div>
                    <button wire:click="unbanGlobal('{{ $entry['ip'] }}')" class="btn btn-ghost btn-sm" title="Desbanear globalmente" style="flex-shrink:0;">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                    </button>
                </div>
                @endforeach
            </div>
            @else
            <div style="text-align:center;padding:30px;color:var(--text-muted);font-size:12px;">
                Sin datos de IPs atacantes disponibles.
            </div>
            @endif
        </div>
    </div>

    {{-- TAB: Jails --}}
    @elseif($activeTab === 'jails')
    <div style="display:grid;grid-template-columns:220px 1fr;gap:16px;align-items:start;">

        {{-- Jail List --}}
        <div class="glass lp-panel">
            <h2 class="panel-title" style="margin-bottom:12px;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);">Jails Activos</h2>
            @foreach($status['jails'] as $jail)
            <button wire:click="selectJail('{{ $jail }}')"
                style="width:100%;text-align:left;background:{{ $selectedJail === $jail ? 'rgba(99,102,241,0.15)' : 'rgba(255,255,255,0.03)' }};border:1px solid {{ $selectedJail === $jail ? 'rgba(99,102,241,0.4)' : 'var(--glass-border)' }};border-radius:8px;padding:9px 12px;cursor:pointer;margin-bottom:5px;display:flex;align-items:center;gap:8px;transition:all 0.15s;">
                <i class="fa-solid fa-bars-staggered" style="color:{{ $selectedJail === $jail ? 'var(--accent-light)' : 'var(--text-muted)' }};font-size:12px;"></i>
                <span style="font-size:12px;font-weight:600;font-family:monospace;color:var(--text-primary);">{{ $jail }}</span>
            </button>
            @endforeach
        </div>

        {{-- Jail Detail --}}
        @if($selectedJail && !empty($jailStatus))
        <div style="display:flex;flex-direction:column;gap:14px;">

            {{-- Jail Stats --}}
            <div class="glass lp-panel">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h2 class="panel-title" style="margin:0;">
                        Jail: <code style="color:var(--accent-light);">{{ $selectedJail }}</code>
                    </h2>
                    <button wire:click="refreshJail" class="btn btn-ghost btn-sm">
                        <i class="fa-solid fa-rotate"></i> Actualizar
                    </button>
                </div>

                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px;">
                    <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);border-radius:8px;padding:12px;text-align:center;">
                        <div style="font-size:22px;font-weight:800;color:var(--danger);">{{ $jailStatus['currently_banned'] }}</div>
                        <div style="font-size:10px;color:var(--text-muted);">Baneadas Ahora</div>
                    </div>
                    <div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.2);border-radius:8px;padding:12px;text-align:center;">
                        <div style="font-size:22px;font-weight:800;color:var(--warning);">{{ $jailStatus['currently_failed'] }}</div>
                        <div style="font-size:10px;color:var(--text-muted);">Fallos Activos</div>
                    </div>
                    <div style="background:rgba(0,0,0,0.15);border-radius:8px;padding:12px;text-align:center;">
                        <div style="font-size:22px;font-weight:800;color:var(--text-primary);">{{ number_format($jailStatus['total_banned']) }}</div>
                        <div style="font-size:10px;color:var(--text-muted);">Total Baneadas</div>
                    </div>
                    <div style="background:rgba(0,0,0,0.15);border-radius:8px;padding:12px;text-align:center;">
                        <div style="font-size:22px;font-weight:800;color:var(--text-primary);">{{ number_format($jailStatus['total_failed']) }}</div>
                        <div style="font-size:10px;color:var(--text-muted);">Total Fallos</div>
                    </div>
                </div>

                {{-- Banned IPs List --}}
                @if(!empty($jailStatus['banned_ips']))
                <h3 style="font-size:13px;font-weight:700;margin-bottom:10px;">IPs Baneadas en este Jail</h3>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    @foreach($jailStatus['banned_ips'] as $ip)
                    <div style="display:flex;align-items:center;gap:4px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);border-radius:6px;padding:4px 8px;">
                        <code style="font-size:12px;color:var(--danger);">{{ $ip }}</code>
                        <button wire:click="confirmUnban('{{ $selectedJail }}','{{ $ip }}')" style="background:none;border:none;cursor:pointer;padding:2px;color:var(--success);font-size:12px;" title="Desbanear">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </button>
                    </div>
                    @endforeach
                </div>
                @else
                <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:12px;">
                    <i class="fa-solid fa-circle-check" style="color:var(--success);margin-right:6px;"></i>
                    No hay IPs baneadas actualmente en este jail.
                </div>
                @endif
            </div>

            {{-- Raw jail status --}}
            <div class="glass lp-panel">
                <h3 class="panel-title" style="margin-bottom:10px;color:var(--text-muted);">
                    <i class="fa-solid fa-terminal" style="margin-right:6px;"></i>
                    fail2ban-client status {{ $selectedJail }}
                </h3>
                <pre style="background:rgba(0,0,0,0.4);border-radius:6px;padding:12px;font-family:monospace;font-size:11px;color:#a6e3a1;line-height:1.6;white-space:pre-wrap;margin:0;">{{ $jailStatus['raw_output'] ?? '' }}</pre>
            </div>

        </div>
        @else
        <div class="glass" style="padding:50px;text-align:center;">
            <i class="fa-solid fa-bars-staggered" style="font-size:36px;opacity:0.2;margin-bottom:12px;display:block;"></i>
            <p style="color:var(--text-secondary);">Selecciona un jail de la lista para ver su estado detallado.</p>
        </div>
        @endif
    </div>

    {{-- TAB: Log --}}
    @elseif($activeTab === 'log')
    <div class="glass lp-panel">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <h2 class="panel-title" style="margin:0;">
                <i class="fa-solid fa-file-lines" style="color:var(--accent-light);margin-right:8px;"></i>
                /var/log/fail2ban.log — últimas 80 líneas
            </h2>
            <button wire:click="$refresh" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-rotate"></i> Actualizar
            </button>
        </div>
        <pre style="background:rgba(0,0,0,0.5);border:1px solid var(--glass-border);border-radius:8px;padding:16px;font-family:monospace;font-size:11px;line-height:1.8;white-space:pre-wrap;max-height:550px;overflow-y:auto;margin:0;">@foreach(array_filter(explode("\n", $logTail)) as $line)@php
            $color = str_contains($line,'Ban') ? '#f38ba8' : (str_contains($line,'Unban') ? '#a6e3a1' : (str_contains($line,'Found') ? '#fab387' : '#cdd6f4'));
        @endphp<span style="color:{{ $color }};">{{ $line }}</span>
@endforeach</pre>
    </div>

    {{-- TAB: History --}}
    @elseif($activeTab === 'history')
    <div class="glass lp-panel">
        <h2 class="panel-title" style="margin-bottom:14px;">
            <i class="fa-solid fa-clock-rotate-left" style="color:var(--accent-light);margin-right:8px;"></i>
            Historial de Acciones (desde LaraPanel)
        </h2>

        @if($history->isEmpty())
        <div style="text-align:center;padding:40px;color:var(--text-muted);">
            <i class="fa-solid fa-inbox" style="font-size:36px;opacity:0.2;margin-bottom:12px;display:block;"></i>
            Sin acciones manuales registradas aún.
        </div>
        @else
        <div style="overflow-x:auto;">
            <table class="lp-table">
                <thead>
                    <tr><th>Acción</th><th>IP</th><th>Jail</th><th>Motivo</th><th>Por</th><th>Cuándo</th></tr>
                </thead>
                <tbody>
                    @foreach($history as $event)
                    <tr>
                        <td>
                            <span class="badge {{ $event->actionBadgeClass() }}" style="font-size:11px;font-weight:700;">
                                {{ strtoupper($event->action) }}
                            </span>
                        </td>
                        <td><code style="font-size:12px;">{{ $event->ip_address }}</code></td>
                        <td><code style="font-size:11px;color:var(--accent-light);">{{ $event->jail }}</code></td>
                        <td style="font-size:11px;color:var(--text-secondary);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $event->reason ?? '—' }}</td>
                        <td style="font-size:11px;color:var(--text-muted);">{{ $event->initiated_by }}</td>
                        <td style="font-size:11px;color:var(--text-muted);" title="{{ $event->created_at->format('Y-m-d H:i:s') }}">{{ $event->created_at->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    @endif

    {{-- Unban Confirm Modal --}}
    @if($unbanIp)
    <div class="lp-modal-backdrop">
        <div class="lp-modal">
            <div style="text-align:center;margin-bottom:20px;">
                <i class="fa-solid fa-circle-check" style="font-size:36px;color:var(--success);margin-bottom:12px;display:block;"></i>
                <h3 class="panel-title" style="font-size:17px;margin-bottom:8px;">Desbanear IP</h3>
                <p style="font-size:13px;color:var(--text-secondary);">
                    Se eliminará el ban de <code style="color:var(--success);">{{ $unbanIp }}</code>
                    del jail <code style="color:var(--accent-light);">{{ $unbanJail }}</code>.
                </p>
                <p style="font-size:11px;color:var(--text-muted);margin-top:8px;">
                    Si la IP sigue atacando, fail2ban la volverá a banear automáticamente.
                </p>
            </div>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button wire:click="$set('unbanIp',null)" class="btn btn-ghost">Cancelar</button>
                <button wire:click="unbanIp" class="btn btn-primary" style="background:var(--success);border-color:var(--success);">
                    <i class="fa-solid fa-circle-check"></i> Sí, desbanear
                </button>
            </div>
        </div>
    </div>
    @endif

    <div wire:loading class="lp-loading-toast">
        <i class="fa-solid fa-spinner fa-spin"></i> Comunicando con fail2ban...
    </div>
    </div>
</div>
