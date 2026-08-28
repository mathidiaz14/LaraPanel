<div class="dropdown-wrapper" style="position:relative;display:inline-block;" x-data="{ open: false }" @click.away="open = false">
    <button @click="open = !open" 
            class="btn btn-ghost btn-sm" 
            style="display:flex;align-items:center;gap:8px;padding:6px 12px;border:1px solid {{ $isRemote ? 'rgba(79,70,229,0.3)' : 'var(--glass-border)' }};background:{{ $isRemote ? 'rgba(79,70,229,0.08)' : 'rgba(255,255,255,0.02)' }};border-radius:var(--radius-sm);color:var(--text-primary);font-size:12px;font-weight:600;cursor:pointer;">
        <span>{{ $currentLabel }}</span>
        <i class="fa-solid fa-chevron-down" style="font-size:10px;opacity:0.6;transition:transform 0.2s;" :style="open ? 'transform:rotate(180deg)' : ''"></i>
    </button>

        <div x-show="open"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95"
         style="position:absolute;right:0;top:100%;margin-top:var(--sp-1);width:240px;background:var(--bg-elevated);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid var(--glass-border);border-radius:var(--radius-sm);box-shadow:var(--shadow-lg);z-index:100;padding:var(--sp-1);display:none;"
         :style="{ display: open ? 'block' : 'none' }">
        
        <div style="font-size:10px;color:var(--text-muted);font-weight:700;padding:var(--sp-1) var(--sp-2);text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--glass-bg);margin-bottom:var(--sp-1);">
            Seleccionar Servidor
        </div>

        @foreach($servers as $srv)
            @php
                $isCurrent = $srv->is_local 
                    ? !$isRemote 
                    : ($isRemote && session('active_server_id') == $srv->id);
                $statusColor = $srv->isOnline() ? '#a6e3a1' : ($srv->status === 'offline' ? '#f38ba8' : '#f9e2af');
            @endphp
            <button type="button" 
                    wire:click="{{ $srv->is_local ? 'selectLocal' : 'selectServer(' . $srv->id . ')' }}"
                    style="width:100%;text-align:left;padding:8px 10px;border-radius:6px;border:none;background:{{ $isCurrent ? 'rgba(255,255,255,0.05)' : 'transparent' }};color:{{ $isCurrent ? 'var(--text-primary)' : 'var(--text-secondary)' }};font-size:12px;font-weight:{{ $isCurrent ? '600' : '400' }};cursor:pointer;display:flex;align-items:center;justify-content:space-between;transition:background 0.2s;"
                    @click="open = false">
                <div style="display:flex;align-items:center;gap:8px;min-width:0;flex:1;">
                    <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:{{ $statusColor }};flex-shrink:0;"></span>
                    <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;">
                        {{ $srv->name }}
                        @if($srv->is_local) <span style="font-size:9px;opacity:0.5;">(local)</span> @endif
                    </span>
                </div>
                @if($isCurrent)
                    <i class="fa-solid fa-check" style="font-size:10px;color:var(--success);margin-left:var(--sp-1);flex-shrink:0;"></i>
                @endif
            </button>
        @endforeach

        <div style="border-top:1px solid var(--glass-bg);margin-top:var(--sp-1);padding-top:var(--sp-1);">
            <a href="{{ route('servers.index') }}" 
                style="display:block;text-align:center;padding:var(--sp-1);font-size:11px;color:var(--accent-light);text-decoration:none;font-weight:600;border-radius:var(--radius-sm);transition:background 0.2s;"
               @click="open = false">
                <i class="fa-solid fa-gear" style="margin-right:4px;"></i> Gestionar Servidores
            </a>
        </div>

    </div>
</div>
