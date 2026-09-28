<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-boxes-stacked" style="color:var(--accent-light);margin-right:10px;"></i>
                Dependencias y CVEs
            </h1>
            <p class="page-subtitle">Actualizaciones disponibles del sistema y avisos de seguridad para servicios críticos.</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <button wire:click="loadData" class="btn btn-ghost btn-sm" wire:loading.attr="disabled">
                <span wire:loading.remove><i class="fa-solid fa-rotate"></i> Actualizar</span>
                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Consultando...</span>
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
            <div @style(['font-size:26px;font-weight:800;' => true, 'color:var(--warning);' => ($updates['count'] ?? 0) > 0, 'color:var(--success);' => ($updates['count'] ?? 0) === 0])>
                {{ $updates['count'] ?? 0 }}
            </div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Actualizaciones disponibles</div>
        </div>
        <div class="glass lp-panel" style="text-align:center;">
            <div @style(['font-size:26px;font-weight:800;' => true, 'color:var(--danger);' => ($security['count'] ?? 0) > 0, 'color:var(--success);' => ($security['count'] ?? 0) === 0])>
                {{ $security['count'] ?? 0 }}
            </div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Paquetes de seguridad</div>
        </div>
        <div class="glass lp-panel" style="text-align:center;">
            <div @style(['font-size:26px;font-weight:800;' => true, 'color:var(--success);' => $security['support'] ?? false, 'color:var(--text-muted);' => !($security['support'] ?? false)])>
                <i class="fa-solid {{ ($security['support'] ?? false) ? 'fa-shield-halved' : 'fa-circle-info' }}"></i>
            </div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">{{ ($security['support'] ?? false) ? 'Soporte debsecan' : 'Heurística apt' }}</div>
        </div>
    </div>

    @if(!empty($security['message']))
        <div class="glass" style="padding:var(--sp-3) var(--sp-4);font-size:12px;color:var(--text-muted);margin-bottom:20px;display:flex;gap:8px;align-items:center;">
            <i class="fa-solid fa-circle-info" style="color:var(--accent-light);"></i>
            {{ $security['message'] }}
        </div>
    @endif

    {{-- Dry run button --}}
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap;">
        <button wire:click="dryRunUpgrade" wire:loading.attr="disabled"
                class="btn {{ empty($selected) ? 'btn-ghost' : 'btn-primary' }} btn-sm"
                title="Simula la actualización sin instalar nada">
            <span wire:loading.remove><i class="fa-solid fa-flask"></i> Simular actualización ({{ count($selected) }})</span>
            <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Simulando...</span>
        </button>
        @if(!empty($selected))
            <span style="font-size:11px;color:var(--text-muted);">Paquetes seleccionados: <strong class="badge badge-info">{{ count($selected) }}</strong></span>
        @endif
    </div>

    {{-- Upgradable packages table --}}
    <div class="glass lp-panel" style="padding:0;overflow:hidden;margin-bottom:24px;">
        <div style="padding:var(--sp-4) var(--sp-6);border-bottom:1px solid var(--glass-border);display:flex;justify-content:space-between;align-items:center;">
            <strong style="font-size:13px;"><i class="fa-solid fa-download" style="margin-right:6px;color:var(--accent-light);"></i> Paquetes actualizables</strong>
            <span style="font-size:11px;color:var(--text-muted);">Fuente: {{ $updates['method'] ?? '—' }}</span>
        </div>
        <div class="table-responsive">
            <table class="lp-table">
                <thead>
                    <tr>
                        <th style="width:30px;"></th>
                        <th>Paquete</th>
                        <th>Versión actual</th>
                        <th>Nueva versión</th>
                        <th>Origen</th>
                        <th>Seguridad</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($updates['packages'] as $pkg)
                        <tr>
                            <td>
                                <input type="checkbox" value="{{ $pkg['name'] }}" wire:model="selected"
                                       style="accent-color:var(--accent-light);width:15px;height:15px;cursor:pointer;">
                            </td>
                            <td style="font-weight:600;font-family:monospace;font-size:12px;">{{ $pkg['name'] }}</td>
                            <td style="font-family:monospace;font-size:12px;color:var(--text-muted);">{{ $pkg['old'] ?: '—' }}</td>
                            <td style="font-family:monospace;font-size:12px;color:var(--success);">{{ $pkg['new'] ?: '—' }}</td>
                            <td style="font-size:12px;">{{ $pkg['source'] ?: '—' }}</td>
                            <td>
                                @if($pkg['security'])
                                    <span class="badge badge-danger"><i class="fa-solid fa-shield-halved"></i> Seguridad</span>
                                @else
                                    <span class="badge badge-secondary">Normal</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted);">
                                <i class="fa-solid fa-circle-check" style="color:var(--success);font-size:20px;display:block;margin-bottom:8px;"></i>
                                Sin actualizaciones pendientes.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Dry run preview --}}
    @if($preview !== null)
    <div class="glass lp-panel" style="padding:var(--sp-5) var(--sp-6);margin-bottom:24px;overflow:hidden;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--sp-3);">
            <strong style="font-size:13px;"><i class="fa-solid fa-flask" style="margin-right:6px;color:var(--warning);"></i> Vista previa (simulación)</strong>
            <span class="badge badge-warning">Solo simulación · nada instalado</span>
        </div>
        @if($previewMessage)
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 var(--sp-3);">{{ $previewMessage }}</p>
        @endif
        @forelse($preview as $pkg)
            <div style="font-size:12px;font-family:monospace;border-top:1px solid var(--glass-border);padding:6px 0;">
                <span style="color:var(--text-muted);">{{ $pkg['name'] }}</span>
                @if($pkg['old'])
                    <span style="color:var(--text-muted);text-decoration:line-through;">{{ $pkg['old'] }}</span>
                    <i class="fa-solid fa-arrow-right" style="margin:0 6px;font-size:10px;color:var(--text-muted);"></i>
                @endif
                <span style="color:var(--success);">{{ $pkg['new'] }}</span>
                @if($pkg['security'])
                    <span class="badge badge-danger" style="margin-left:8px;">Seguridad</span>
                @endif
            </div>
        @empty
            <p style="font-size:12px;color:var(--text-muted);margin:0;">La simulación no produjo cambios (paquetes ya al día).</p>
        @endforelse
    </div>
    @endif

    {{-- Core service versions --}}
    <h2 class="panel-title" style="margin-bottom:14px;">Servicios críticos y CVEs</h2>
    <div class="glass lp-panel" style="padding:0;overflow:hidden;margin-bottom:24px;">
        <div class="table-responsive">
            <table class="lp-table">
                <thead>
                    <tr>
                        <th>Servicio</th>
                        <th>Paquete</th>
                        <th>Versión instalada</th>
                        <th>CVE detectado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($core as $svc)
                        @php $hasCve = in_array($svc['package'], $securityNames, true); @endphp
                        <tr>
                            <td style="font-weight:600;">{{ strtoupper($svc['key']) }}</td>
                            <td style="font-family:monospace;font-size:12px;color:var(--text-muted);">{{ $svc['package'] }}</td>
                            <td style="font-family:monospace;font-size:12px;">{{ $svc['version'] ?? '—' }}</td>
                            <td>
                                @if($hasCve)
                                    <span class="badge badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> Actualización disponible</span>
                                @else
                                    <span class="badge badge-success"><i class="fa-solid fa-check"></i> Sin avisos</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;padding:32px;color:var(--text-muted);">
                                No se pudieron consultar las versiones de los servicios.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>