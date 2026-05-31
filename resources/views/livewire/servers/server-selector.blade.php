<div class="dropdown-wrapper" style="position:relative;display:inline-block;" x-data="{ open: false }" @click.away="open = false">
    <button @click="open = !open" 
            class="btn btn-ghost btn-sm" 
            style="display:flex;align-items:center;gap:8px;padding:6px 12px;border:1px solid {{ $isRemote ? 'rgba(137,180,250,0.3)' : 'var(--glass-border)' }};background:{{ $isRemote ? 'rgba(137,180,250,0.08)' : 'rgba(255,255,255,0.02)' }};border-radius:8px;color:var(--text-primary);font-size:12px;font-weight:600;cursor:pointer;">
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
         style="position:absolute;right:0;top:100%;margin-top:6px;width:240px;background:rgba(30,30,46,0.95);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid var(--glass-border);border-radius:8px;box-shadow:0 10px 25px -5px rgba(0, 0, 0, 0.5);z-index:100;padding:6px;display:none;" 
         :style="{ display: open ? 'block' : 'none' }">
        
        <div style="font-size:10px;color:var(--text-muted);font-weight:700;padding:6px 8px;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid rgba(255,255,255,0.05);margin-bottom:4px;">
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
                    <i class="fa-solid fa-check" style="font-size:10px;color:#a6e3a1;margin-left:6px;flex-shrink:0;"></i>
                @endif
            </button>
        @endforeach

        <div style="border-top:1px solid rgba(255,255,255,0.05);margin-top:4px;padding-top:4px;">
            <a href="{{ route('servers.index') }}" 
               style="display:block;text-align:center;padding:6px;font-size:11px;color:#89b4fa;text-decoration:none;font-weight:600;border-radius:4px;transition:background 0.2s;"
               @click="open = false">
                <i class="fa-solid fa-gear" style="margin-right:4px;"></i> Gestionar Servidores
            </a>
        </div>

    </div>
</div>
