<div style="display:flex;height:calc(100vh - 140px);gap:20px;">
    {{-- Sidebar Selector --}}
    <div class="glass" style="width:280px;display:flex;flex-direction:column;padding:0;">
        <div style="padding:20px;border-bottom:1px solid var(--glass-border);">
            <h3 style="font-size:16px;font-weight:700;"><i class="fa-solid fa-file-waveform" style="color:var(--accent-light);margin-right:8px;"></i> Archivos de Log</h3>
        </div>
        
        <div style="flex:1;overflow-y:auto;padding:10px;">
            @php $currentType = ''; @endphp
            @foreach($availableLogs as $log)
                @if($currentType !== $log['type'])
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:700;margin:16px 10px 8px;">
                        {{ $log['type'] === 'panel' ? 'Panel' : ($log['type'] === 'system' ? 'Sistema' : ($log['type'] === 'service' ? 'Servicios' : 'Dominios')) }}
                    </div>
                    @php $currentType = $log['type']; @endphp
                @endif
                
                <button wire:click="selectLog('{{ $log['path'] }}')" 
                        style="width:100%;text-align:left;padding:10px;border:none;background:{{ $selectedLogPath === $log['path'] ? 'rgba(99,102,241,0.1)' : 'transparent' }};color:{{ $selectedLogPath === $log['path'] ? 'var(--text-primary)' : 'var(--text-secondary)' }};border-radius:6px;cursor:pointer;font-size:13px;font-weight:{{ $selectedLogPath === $log['path'] ? '600' : '400' }};transition:all 0.2s;display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-file-lines" style="color:{{ $selectedLogPath === $log['path'] ? 'var(--accent-light)' : 'var(--text-muted)' }};"></i>
                    <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $log['name'] }}</span>
                </button>
            @endforeach

            @if(empty($availableLogs))
                <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">No hay logs disponibles.</div>
            @endif
        </div>
    </div>

    {{-- Log Viewer Area --}}
    <div class="glass" style="flex:1;display:flex;flex-direction:column;padding:0;overflow:hidden;">
        
        {{-- Toolbar --}}
        <div style="padding:16px 20px;border-bottom:1px solid var(--glass-border);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:12px;flex:1;">
                <div class="form-group" style="margin:0;max-width:300px;flex:1;">
                    <div style="position:relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:10px;color:var(--text-muted);"></i>
                        <input type="text" wire:model.live.debounce.500ms="searchQuery" class="form-input" placeholder="Buscar en el log..." style="padding-left:36px;height:36px;font-size:13px;">
                    </div>
                </div>
                
                <div class="form-group" style="margin:0;">
                    <select wire:model.live="linesToFetch" class="form-input" style="height:36px;font-size:13px;">
                        <option value="50">Últimas 50 líneas</option>
                        <option value="100">Últimas 100 líneas</option>
                        <option value="500">Últimas 500 líneas</option>
                        <option value="1000">Últimas 1000 líneas</option>
                    </select>
                </div>
            </div>
            
            <div style="display:flex;align-items:center;gap:10px;">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;margin-right:10px;">
                    <input type="checkbox" wire:model.live="autoRefresh"> Auto-refrescar
                </label>
                
                <button wire:click="refreshLog" class="btn btn-ghost btn-sm" title="Actualizar ahora">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
                
                @if(auth()->user()?->isAdmin())
                <button wire:click="clearLog" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que deseas vaciar este archivo de log?')" title="Vaciar Archivo">
                    <i class="fa-solid fa-eraser"></i> Limpiar
                </button>
                @endif
            </div>
        </div>

        {{-- Alerts --}}
        @if(session()->has('message'))
            <div style="padding:10px 20px;background:rgba(39,201,63,0.1);color:var(--success);font-size:13px;border-bottom:1px solid rgba(39,201,63,0.2);">
                <i class="fa-solid fa-check-circle"></i> {{ session('message') }}
            </div>
        @endif
        @if(session()->has('error'))
            <div style="padding:10px 20px;background:rgba(255,95,86,0.1);color:var(--danger);font-size:13px;border-bottom:1px solid rgba(255,95,86,0.2);">
                <i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}
            </div>
        @endif

        {{-- The Terminal --}}
        <div style="flex:1;background:#0d1117;overflow:auto;padding:16px;position:relative;" @if($autoRefresh) wire:poll.3s="refreshLog" @endif>
            <pre style="margin:0;font-family:'Courier New', Courier, monospace;font-size:13px;color:#c9d1d9;white-space:pre-wrap;word-wrap:break-word;line-height:1.5;">@if($logContent){!! preg_replace('/(error|fatal|exception|failed|denied|crit)/i', '<span style="color:#ff7b72;">$1</span>', preg_replace('/(warn|warning)/i', '<span style="color:#d2a8ff;">$1</span>', htmlspecialchars($logContent))) !!}@else<span style="color:#8b949e;">Esperando contenido...</span>@endif</pre>
        </div>
        
        <div style="padding:8px 20px;background:rgba(255,255,255,0.02);border-top:1px solid var(--glass-border);font-size:11px;color:var(--text-muted);display:flex;justify-content:space-between;">
            <span>Ruta: {{ $selectedLogPath ?? 'Ninguno' }}</span>
            <span wire:loading wire:target="refreshLog, loadLogContent" style="color:var(--accent-light);">Actualizando...</span>
        </div>
    </div>
</div>
