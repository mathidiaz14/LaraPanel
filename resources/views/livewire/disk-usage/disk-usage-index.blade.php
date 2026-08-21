<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-chart-pie" style="color:var(--accent-light);margin-right:10px;"></i>
                Uso de Disco
            </h1>
            <p class="page-subtitle">Analiza qué ocupa espacio en las zonas clave del servidor.</p>
        </div>
        <button wire:click="refreshScan" class="btn btn-ghost btn-sm" wire:loading.attr="disabled">
            <span wire:loading.remove><i class="fa-solid fa-rotate"></i> Re-escanear</span>
            <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Escaneando...</span>
        </button>
    </div>

    {{-- Alerts --}}
    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    {{-- Partitions --}}
    <h2 class="panel-title" style="margin-bottom:14px;">Particiones</h2>
    <div class="stats-row" style="margin-bottom:28px;">
        @foreach($partitions as $part)
        <div class="glass lp-panel">
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px;">
                <span style="font-size:13px;font-weight:700;font-family:monospace;">{{ $part['mount'] }}</span>
                <span style="font-size:12px;color:var(--text-muted);">{{ $part['device'] }}</span>
            </div>
            <div style="height:6px;background:rgba(255,255,255,0.05);border-radius:3px;overflow:hidden;margin-bottom:8px;">
                <div style="width:{{ min(100, $part['percent']) }}%;height:100%;border-radius:3px;background:{{ $part['percent'] > 90 ? 'var(--danger)' : ($part['percent'] > 75 ? 'var(--warning)' : '#27c93f') }};transition:width 0.5s;"></div>
            </div>
            <div style="font-size:11px;color:var(--text-muted);">
                {{ \App\Services\MonitoringService::formatBytes($part['used']) }} de {{ \App\Services\MonitoringService::formatBytes($part['size']) }}
                — <strong style="color:{{ $part['percent'] > 90 ? 'var(--danger)' : 'var(--text-primary)' }}">{{ $part['percent'] }}%</strong>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Path Selector + Breadcrumb --}}
    <div class="filters-bar" style="flex-wrap:wrap;">
        <label style="font-size:12px;color:var(--text-secondary);">Zona:</label>
        <select wire:model.live.change="path" class="form-input" style="max-width:280px;font-size:12px;">
            @foreach(\App\Services\DiskUsageService::ALLOWED_ROOTS as $root)
                <option value="{{ $root }}">{{ $root }}</option>
            @endforeach
        </select>

        <div style="display:flex;align-items:center;gap:4px;font-size:13px;flex-wrap:wrap;" wire:loading.remove>
            @php
                // Build breadcrumb parts from the absolute path
                $crumbs = [];
                $acc = '';
                foreach (explode('/', trim($scan['path'] ?? $path, '/')) as $seg) {
                    if ($seg === '') continue;
                    $acc .= '/' . $seg;
                    $crumbs[] = ['name' => $seg, 'path' => $acc];
                }
            @endphp
            <i class="fa-solid fa-hard-drive" style="color:var(--text-muted);"></i>
            @foreach($crumbs as $crumb)
                <span style="color:var(--text-muted);">/</span>
                @if(!$loop->last)
                    <a href="#" wire:click.prevent="navigate('{{ addslashes($crumb['path']) }}')" style="color:var(--text-secondary);text-decoration:none;">{{ $crumb['name'] }}</a>
                @else
                    <strong style="color:var(--text-primary);">{{ $crumb['name'] }}</strong>
                @endif
            @endforeach
        </div>

        @php $canGoUp = $scan && dirname($scan['path']) !== $scan['path']; @endphp
        <button wire:click="goUp" class="btn btn-ghost btn-sm" @if(!$canGoUp) disabled @endif title="Subir un nivel">
            <i class="fa-solid fa-level-up-alt"></i> Subir
        </button>

        <span style="font-size:11px;color:var(--text-muted);margin-left:auto;">
            @if($scan)
                Escaneado: {{ \Carbon\Carbon::parse($scan['scanned_at'])->format('H:i:s') }} (cache 60s)
            @endif
        </span>
    </div>

    {{-- Directory Table --}}
    <div class="glass lp-panel" style="padding:0;overflow:hidden;">
        <div class="table-responsive">
            <table class="lp-table">
                <thead>
                    <tr>
                        <th style="min-width:220px;">Directorio</th>
                        <th style="width:40%;">Tamaño</th>
                        <th>% del Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @if($scan && $scan['total'] > 0)
                        <tr style="background:rgba(99,102,241,0.05);">
                            <td style="font-weight:700;"><i class="fa-solid fa-folder-open" style="color:var(--accent-light);margin-right:8px;"></i>{{ $scan['path'] }}</td>
                            <td colspan="2"><strong>{{ \App\Services\MonitoringService::formatBytes($scan['total']) }}</strong></td>
                            <td></td>
                        </tr>
                    @endif
                    @forelse(($scan['items'] ?? []) as $item)
                        <tr>
                            <td>
                                <i class="fa-{{ $item['is_dir'] ? 'solid fa-folder' : 'regular fa-file' }}" style="color:var({{ $item['is_dir'] ? '--warning' : '--text-muted' }});margin-right:8px;"></i>
                                {{ $item['name'] }}
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="flex:1;height:6px;background:rgba(255,255,255,0.05);border-radius:3px;overflow:hidden;">
                                        <div style="width:{{ min(100, $item['pct']) }}%;height:100%;border-radius:3px;background:linear-gradient(90deg,#6366f1,#a78bfa);transition:width 0.5s;"></div>
                                    </div>
                                    <span style="font-size:12px;white-space:nowrap;min-width:64px;text-align:right;">{{ \App\Services\MonitoringService::formatBytes($item['bytes']) }}</span>
                                </div>
                            </td>
                            <td style="white-space:nowrap;">{{ number_format($item['pct'], 1) }}%</td>
                            <td style="text-align:right;">
                                @if($item['is_dir'])
                                    <button wire:click="navigate('{{ addslashes($item['path']) }}')" class="btn btn-ghost btn-sm" wire:loading.attr="disabled" title="Explorar subdirectorios">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;padding:32px;color:var(--text-muted);">
                                @if($errorMessage === '')
                                    <i class="fa-solid fa-spinner fa-spin"></i> Escaneando directorio... (puede tardar en rutas grandes)
                                @else
                                    No hay datos para mostrar.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($scan && ($scan['partial'] ?? false))
    <div style="margin-top:10px;font-size:11px;color:var(--text-muted);">
        <i class="fa-solid fa-circle-info"></i> Algunos subdirectorios no pudieron leerse (permisos). El tamaño mostrado puede ser parcial.
    </div>
    @endif
</div>
