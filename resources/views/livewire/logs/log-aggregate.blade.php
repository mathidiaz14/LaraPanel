@php
    $levelColors = [
        'error' => ['var(--danger)', 'rgba(239,68,68,0.15)'],
        'warn'  => ['var(--warning)', 'rgba(234,179,8,0.12)'],
        'info'  => ['var(--info)', 'rgba(56,189,248,0.10)'],
        'debug' => ['var(--text-muted)', 'rgba(148,163,184,0.10)'],
    ];
    $typeLabel = [
        'nginx'   => 'Nginx',
        'php-fpm' => 'PHP-FPM',
        'mysql'   => 'MySQL',
        'mail'    => 'Mail',
        'laravel' => 'Panel',
        'audit'   => 'Auditoría',
    ];
@endphp

<div class="glass lp-panel" style="padding:0;overflow:hidden;">
    {{-- Toolbar --}}
    <div style="padding:16px 20px;border-bottom:1px solid var(--glass-border);">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
            <h2 class="panel-title" style="margin:0;font-size:16px;">
                <i class="fa-solid fa-layer-group" style="color:var(--accent-light);margin-right:8px;"></i> Feed agrupado de Logs
            </h2>
            <button wire:click="resetFilters" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Restablecer filtros</button>
        </div>

        {{-- Source chips --}}
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
            @foreach($sourceDefs as $source)
                @php $active = empty($selectedSources) || in_array($source['key'], $selectedSources, true); @endphp
                <button wire:click="toggleSource('{{ $source['key'] }}')"
                    style="border:1px solid {{ $active ? 'rgba(99,102,241,0.5)' : 'var(--glass-border)' }};background:{{ $active ? 'rgba(99,102,241,0.12)' : 'transparent' }};color:{{ $active ? 'var(--text-primary)' : 'var(--text-muted)' }};padding:5px 11px;border-radius:20px;font-size:12px;cursor:pointer;">
                    <i class="fa-regular fa-circle-check" style="margin-right:5px;color:{{ $active ? 'var(--accent-light)' : 'var(--text-muted)' }};"></i>
                    {{ $source['label'] }}
                </button>
            @endforeach
        </div>

        {{-- Level + search --}}
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <div style="display:flex;gap:6px;">
                @foreach(['error' => 'Errores', 'warn' => 'Warnings', 'info' => 'Info', 'debug' => 'Debug'] as $code => $label)
                    <button wire:click="setLevel('{{ $code }}')"
                        style="border:1px solid {{ $level === $code ? $levelColors[$code][0] : 'var(--glass-border)' }};background:{{ $level === $code ? $levelColors[$code][1] : 'transparent' }};color:{{ $level === $code ? $levelColors[$code][0] : 'var(--text-muted)' }};padding:5px 12px;border-radius:6px;font-size:12px;cursor:pointer;">
                        {{ $label }} <span style="opacity:0.6;">({{ $result['byLevel'][$code] ?? 0 }})</span>
                    </button>
                @endforeach
                @if($level)
                    <button wire:click="setLevel(null)" class="btn btn-ghost btn-sm" style="font-size:12px;"><i class="fa-solid fa-xmark"></i> Todos</button>
                @endif
            </div>

            <div class="form-group" style="margin:0;flex:1 1 200px;min-width:0;">
                <div style="position:relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:10px;color:var(--text-muted);"></i>
                    <input type="text" wire:model.live.debounce.400ms="query" class="form-input" placeholder="Buscar en todo el feed..." style="padding-left:36px;height:36px;font-size:13px;">
                </div>
            </div>
        </div>
    </div>

    {{-- Summary --}}
    <div style="padding:10px 20px;border-bottom:1px solid var(--glass-border);display:flex;gap:18px;flex-wrap:wrap;font-size:12px;color:var(--text-muted);">
        <span><i class="fa-solid fa-filter"></i> Fuentes activas: {{ count($result['sources']) }}</span>
        <span><i class="fa-solid fa-list"></i> Líneas: <strong style="color:var(--text-primary);">{{ $result['total'] }}</strong></span>
        @foreach($result['sources'] as $src)
            <span style="display:inline-flex;align-items:center;gap:5px;">
                <span style="width:7px;height:7px;border-radius:50%;background:var(--accent-light);display:inline-block;"></span>
                {{ $typeLabel[$src['type']] ?? $src['type'] }}: {{ $src['count'] }}
            </span>
        @endforeach
    </div>

    {{-- Feed --}}
    <div style="max-height:calc(100vh - 380px);overflow-y:auto;">
        @if($result['total'] === 0)
            <div style="text-align:center;padding:clamp(24px,8vw,60px) 16px;color:var(--text-muted);">
                <i class="fa-solid fa-inbox" style="font-size:36px;opacity:0.3;margin-bottom:12px;display:block;"></i>
                No hay líneas que coincidan con los filtros actuales.
            </div>
        @else
            @foreach($result['feed'] as $item)
                @php [$color, $bg] = $levelColors[$item['level']] ?? $levelColors['info']; @endphp
                <div style="padding:8px 20px;border-bottom:1px solid rgba(255,255,255,0.03);display:flex;gap:12px;align-items:flex-start;font-size:12px;">
                    <span style="white-space:nowrap;margin-top:2px;">
                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $color }};margin-right:6px;"></span>
                        <span style="color:var(--text-muted);font-size:10px;text-transform:uppercase;letter-spacing:0.5px;">{{ $item['level'] ?: '—' }}</span>
                    </span>
                    <span style="white-space:nowrap;margin-top:2px;">
                        <span style="background:{{ $bg }};color:{{ $color }};border:1px solid {{ $color }}33;border-radius:4px;padding:1px 7px;font-size:10px;">{{ $item['type'] }}</span>
                    </span>
                    <span style="flex:1;font-family:'Courier New',Courier,monospace;color:var(--text-secondary);white-space:pre-wrap;word-break:break-word;line-height:1.5;">{{ $item['line'] }}</span>
                </div>
            @endforeach
        @endif
    </div>

    {{-- Load more --}}
    @if($perSource < 2000)
        <div style="padding:14px;text-align:center;border-top:1px solid var(--glass-border);">
            <button wire:click="loadMore" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-angle-down"></i> Cargar más ({{ $perSource }} líneas por fuente)
            </button>
            <span wire:loading wire:target="toggleSource,setLevel,loadMore" style="margin-left:10px;color:var(--accent-light);font-size:12px;">Agrupando...</span>
        </div>
    @endif
</div>