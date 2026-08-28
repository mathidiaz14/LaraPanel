<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-gears" style="color:var(--accent-light);margin-right:10px;"></i>
                Gestión de Servicios
            </h1>
            <p class="page-subtitle">Inicia, detén o reinicia los servicios del servidor.</p>
        </div>
        <button wire:click="loadServices" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-rotate"></i> Actualizar
        </button>
    </div>

    {{-- Alerts --}}
    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    {{-- Summary --}}
    <div class="stats-row" style="margin-bottom:20px;">
        <div class="glass lp-panel" style="text-align:center;">
            <div style="font-size:24px;font-weight:800;color:var(--success);">{{ $summary['active'] }} / {{ $summary['total'] }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Servicios Activos</div>
        </div>
        <div class="glass lp-panel" style="text-align:center;border-color:{{ $summary['critical_down'] > 0 ? 'rgba(239,68,68,0.4)' : 'transparent' }};">
            <div style="font-size:24px;font-weight:800;color:{{ $summary['critical_down'] > 0 ? 'var(--danger)' : 'var(--success)' }};">{{ $summary['critical_down'] }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Críticos Caídos</div>
        </div>
    </div>

    @if($summary['critical_down'] > 0)
    <div class="alert alert-danger" style="margin-bottom:20px;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Hay servicios críticos detenidos. Algunas funciones del panel y de los sitios alojados pueden no estar operativas.
    </div>
    @endif

    {{-- Services Grid --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:16px;">
        @foreach($services as $svc)
        <div class="glass-elevated" style="padding:20px;display:flex;flex-direction:column;gap:14px;{{ !$svc['active'] && $svc['critical'] ? 'border-color:rgba(239,68,68,0.35);' : '' }}">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:42px;height:42px;border-radius:10px;background:rgba(79,70,229,0.1);color:var(--accent-light);display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0;">
                    <i class="{{ $svc['icon'] }}"></i>
                </div>
                <div style="min-width:0;flex:1;">
                    <div style="font-size:15px;font-weight:700;display:flex;align-items:center;gap:8px;">
                        {{ $svc['label'] }}
                        @if($svc['critical'])
                            <span class="badge badge-accent" title="Servicio crítico para el funcionamiento del panel">crítico</span>
                        @endif
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $svc['description'] }}</div>
                </div>
                <div style="flex-shrink:0;font-size:11px;font-weight:700;display:flex;align-items:center;gap:5px;color:{{ $svc['active'] ? 'var(--success)' : 'var(--danger)' }};">
                    <span style="width:8px;height:8px;border-radius:50%;background:currentColor;box-shadow:0 0 8px currentColor;"></span>
                    {{ $svc['active'] ? 'ACTIVO' : 'DETENIDO' }}
                </div>
            </div>

            <div style="display:flex;gap:6px;justify-content:flex-end;">
                <button wire:click="askAction('{{ $svc['name'] }}', '{{ addslashes($svc['label']) }}', 'start', {{ $svc['critical'] ? 'true' : 'false' }})"
                        class="btn btn-ghost btn-sm" @if($svc['active']) disabled title="Ya está activo" @endif wire:loading.attr="disabled">
                    <i class="fa-solid fa-play" style="color:var(--success);"></i> Iniciar
                </button>
                <button wire:click="askAction('{{ $svc['name'] }}', '{{ addslashes($svc['label']) }}', 'restart', {{ $svc['critical'] ? 'true' : 'false' }})"
                        class="btn btn-ghost btn-sm" style="color:var(--warning);" wire:loading.attr="disabled">
                    <i class="fa-solid fa-arrows-rotate"></i> Reiniciar
                </button>
                <button wire:click="askAction('{{ $svc['name'] }}', '{{ addslashes($svc['label']) }}', 'stop', {{ $svc['critical'] ? 'true' : 'false' }})"
                        class="btn btn-danger btn-sm" @if(!$svc['active']) disabled title="Ya está detenido" @endif wire:loading.attr="disabled">
                    <i class="fa-solid fa-stop"></i> Detener
                </button>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Action Confirmation Modal --}}
    @if($pendingService !== null)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;"
         wire:click="cancelAction">
        <div class="glass-elevated" style="max-width:440px;width:100%;padding:28px;border:1px solid rgba(245,158,11,0.35);" onclick="event.stopPropagation()">
            <h3 style="margin:0 0 12px;font-size:17px;display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);"></i>
                Confirmar acción
            </h3>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;line-height:1.6;">
                Vas a <strong>{{ $pendingAction === 'restart' ? 'reiniciar' : 'detener' }}</strong> el servicio
                <strong>{{ $pendingLabel }}</strong>.
                @if($pendingCritical)
                <br><br><span style="color:var(--danger);"><i class="fa-solid fa-circle-exclamation"></i> Este es un servicio crítico: durante la operación los sitios web / correo / bases de datos pueden dejar de responder unos segundos.</span>
                @else
                El servicio no estará disponible durante la operación.
                @endif
            </p>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button wire:click="cancelAction" class="btn btn-ghost">Cancelar</button>
                <button wire:click="runConfirmed" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove><i class="fa-solid fa-check"></i> Confirmar</span>
                    <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Ejecutando...</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
