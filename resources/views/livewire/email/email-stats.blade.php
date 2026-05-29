<div>
    @include('livewire.email._email-nav', ['active' => 'stats'])

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">Estadísticas de Email</h1>
            <p style="color:var(--text-secondary);font-size:13px;">Monitorea el uso de cuota y el estado de todos los buzones por dominio.</p>
        </div>
    </div>

    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif

    {{-- Global summary --}}
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;">
        <div class="glass" style="padding:18px;text-align:center;">
            <div style="font-size:30px;font-weight:800;color:var(--accent-light);">{{ $globalAccounts }}</div>
            <div style="font-size:11px;color:var(--text-secondary);">Total de Buzones</div>
        </div>
        <div class="glass" style="padding:18px;text-align:center;">
            <div style="font-size:30px;font-weight:800;color:var(--success);">{{ $activeAccounts }}</div>
            <div style="font-size:11px;color:var(--text-secondary);">Buzones Activos</div>
        </div>
        <div class="glass" style="padding:18px;text-align:center;">
            <div style="font-size:30px;font-weight:800;color:var(--danger);">{{ $globalAccounts - $activeAccounts }}</div>
            <div style="font-size:11px;color:var(--text-secondary);">Buzones Suspendidos</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start;">

        {{-- Domain selector --}}
        <div class="glass" style="padding:18px;">
            <h2 style="font-size:13px;font-weight:700;margin-bottom:12px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;">Dominio</h2>
            @foreach($domains as $domain)
            @php
                $domAccounts = \App\Models\EmailAccount::where('domain_id', $domain->id)->count();
            @endphp
            <button wire:click="selectDomain({{ $domain->id }})"
                style="width:100%;text-align:left;background:{{ $selectedDomainId === $domain->id ? 'rgba(99,102,241,0.15)' : 'rgba(255,255,255,0.03)' }};border:1px solid {{ $selectedDomainId === $domain->id ? 'rgba(99,102,241,0.4)' : 'var(--glass-border)' }};border-radius:8px;padding:9px 12px;cursor:pointer;margin-bottom:5px;display:flex;align-items:center;justify-content:space-between;transition:all 0.2s;">
                <span style="font-size:12px;font-weight:600;color:var(--text-primary);">{{ $domain->name }}</span>
                <span style="font-size:10px;background:rgba(255,255,255,0.08);border-radius:10px;padding:1px 7px;color:var(--text-muted);">{{ $domAccounts }}</span>
            </button>
            @endforeach
        </div>

        {{-- Domain detail --}}
        @if($selectedDomainId && $accounts->isNotEmpty())
        <div style="display:flex;flex-direction:column;gap:14px;">

            {{-- Domain quota summary --}}
            <div class="glass" style="padding:18px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                    <h2 style="font-size:14px;font-weight:700;margin:0;">Uso Total del Dominio</h2>
                    <button wire:click="refreshUsage" class="btn btn-ghost btn-sm" wire:loading.attr="disabled">
                        <span wire:loading.remove><i class="fa-solid fa-sync"></i> Actualizar</span>
                        <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i></span>
                    </button>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:12px;">
                    <div style="text-align:center;background:rgba(0,0,0,0.15);border-radius:8px;padding:12px;">
                        <div style="font-size:20px;font-weight:700;color:var(--text-primary);">{{ $accounts->count() }}</div>
                        <div style="font-size:11px;color:var(--text-muted);">Buzones</div>
                    </div>
                    <div style="text-align:center;background:rgba(0,0,0,0.15);border-radius:8px;padding:12px;">
                        @php
                            $usedFmt = $totalUsed < 1073741824
                                ? round($totalUsed / 1048576, 1) . ' MB'
                                : round($totalUsed / 1073741824, 2) . ' GB';
                        @endphp
                        <div style="font-size:20px;font-weight:700;color:var(--accent-light);">{{ $usedFmt }}</div>
                        <div style="font-size:11px;color:var(--text-muted);">Espacio Usado</div>
                    </div>
                    <div style="text-align:center;background:rgba(0,0,0,0.15);border-radius:8px;padding:12px;">
                        @php
                            $quotaFmt = $totalQuota > 0
                                ? ($totalQuota < 1073741824 ? round($totalQuota/1048576,1).' MB' : round($totalQuota/1073741824,2).' GB')
                                : 'Ilimitado';
                        @endphp
                        <div style="font-size:20px;font-weight:700;color:var(--text-primary);">{{ $quotaFmt }}</div>
                        <div style="font-size:11px;color:var(--text-muted);">Cuota Total</div>
                    </div>
                </div>
                @if($totalQuota > 0)
                <div style="height:8px;border-radius:4px;background:rgba(255,255,255,0.06);overflow:hidden;">
                    <div style="height:100%;width:{{ min($usedPercent, 100) }}%;background:{{ $usedPercent > 85 ? 'var(--danger)' : ($usedPercent > 60 ? 'var(--warning)' : 'var(--success)') }};border-radius:4px;transition:width 0.5s;"></div>
                </div>
                <div style="text-align:right;font-size:11px;color:var(--text-muted);margin-top:4px;">{{ $usedPercent }}% utilizado</div>
                @endif
            </div>

            {{-- Per-mailbox table --}}
            <div class="glass" style="padding:20px;">
                <h2 style="font-size:14px;font-weight:700;margin-bottom:14px;">Detalle por Buzón</h2>
                <div style="overflow-x:auto;">
                    <table class="table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Nombre</th>
                                <th>Usado</th>
                                <th>Cuota</th>
                                <th>Uso %</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accounts as $account)
                            @php
                                $pct = $account->quota_bytes > 0
                                    ? min(round(($account->used_bytes / $account->quota_bytes) * 100, 1), 100)
                                    : 0;
                            @endphp
                            <tr>
                                <td>
                                    <strong style="font-size:13px;color:var(--text-primary);">{{ $account->email }}</strong>
                                </td>
                                <td>
                                    <span style="font-size:12px;color:var(--text-secondary);">{{ $account->display_name ?? '—' }}</span>
                                </td>
                                <td>
                                    <span style="font-size:12px;font-weight:600;color:{{ $pct > 85 ? 'var(--danger)' : 'var(--text-primary)' }};">
                                        {{ $account->usedFormatted() }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:12px;color:var(--text-secondary);">{{ $account->quotaFormatted() }}</span>
                                </td>
                                <td style="min-width:100px;">
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <div style="flex:1;height:5px;border-radius:3px;background:rgba(255,255,255,0.08);overflow:hidden;">
                                            <div style="height:100%;width:{{ $pct }}%;background:{{ $pct > 85 ? 'var(--danger)' : ($pct > 60 ? 'var(--warning)' : 'var(--success)') }};border-radius:3px;"></div>
                                        </div>
                                        <span style="font-size:10px;color:var(--text-muted);white-space:nowrap;">{{ $pct }}%</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $account->is_active ? 'badge-success' : 'badge-muted' }}" style="font-size:10px;">
                                        {{ $account->is_active ? 'Activo' : 'Suspendido' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        @elseif($selectedDomainId && $accounts->isEmpty())
        <div class="glass" style="padding:40px;text-align:center;">
            <i class="fa-solid fa-inbox" style="font-size:36px;opacity:0.2;margin-bottom:12px;display:block;"></i>
            <p style="color:var(--text-secondary);">No hay buzones en este dominio. <a href="{{ route('email.index') }}" style="color:var(--accent-light);">Crear uno</a>.</p>
        </div>
        @else
        <div class="glass" style="padding:60px;text-align:center;">
            <i class="fa-solid fa-chart-bar" style="font-size:40px;opacity:0.2;margin-bottom:12px;display:block;"></i>
            <p style="color:var(--text-secondary);">Selecciona un dominio para ver las estadísticas de sus buzones.</p>
        </div>
        @endif

    </div>

    <div wire:loading style="position:fixed;bottom:24px;right:24px;z-index:300;">
        <div class="glass" style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-spinner fa-spin"></i> Calculando uso...
        </div>
    </div>
</div>
