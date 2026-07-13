<div>
    {{-- Header --}}
    <div class="header-banner">
        <div>
            <h1 class="page-title">Monitor de Servicios (Uptime)</h1>
            <p class="page-subtitle">Monitorea tus sitios web y contenedores Docker en tiempo real.</p>
        </div>
        <button wire:click="$set('showCreateModal', true)" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Nuevo Monitor
        </button>
    </div>

    {{-- Alerts --}}
    @if($successMessage)
        <div class="alert alert-success" style="margin-bottom:20px;">{{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="alert alert-error" style="margin-bottom:20px;">{{ $errorMessage }}</div>
    @endif

    {{-- Monitor List --}}
    @if($monitors->isEmpty())
        <div class="empty-state glass">
            <div class="empty-icon"><i class="fa-solid fa-heart-pulse"></i></div>
            <h3>No hay monitores activos</h3>
            <p>Comienza añadiendo un sitio web o contenedor Docker para vigilar su estado.</p>
            <button wire:click="$set('showCreateModal', true)" class="btn btn-primary mt-4">Añadir Monitor</button>
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(400px, 1fr));gap:20px;">
            @foreach($monitors as $monitor)
            <div class="glass" style="padding:20px;display:flex;flex-direction:column;gap:15px;position:relative;">
                
                {{-- Status Badge --}}
                <div style="position:absolute;top:20px;right:20px;">
                    @if($monitor->status === 'up')
                        <span class="badge badge-success"><i class="fa-solid fa-check"></i> EN LÍNEA</span>
                    @elseif($monitor->status === 'down')
                        <span class="badge badge-danger"><i class="fa-solid fa-xmark"></i> CAÍDO</span>
                    @elseif($monitor->status === 'paused')
                        <span class="badge badge-muted"><i class="fa-solid fa-pause"></i> PAUSADO</span>
                    @else
                        <span class="badge badge-warning"><i class="fa-solid fa-hourglass-half"></i> ESPERANDO</span>
                    @endif
                </div>

                {{-- Info --}}
                <div>
                    <h3 style="margin:0 0 5px;font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-{{ $monitor->type === 'http' ? 'globe' : 'box-open' }}" style="color:var(--accent-light);"></i>
                        {{ $monitor->name }}
                    </h3>
                    <div style="font-size:12px;color:var(--text-muted);font-family:monospace;">
                        {{ $monitor->target }}
                    </div>
                </div>

                {{-- Chart Area --}}
                @php
                    $stats = $chartData[$monitor->id] ?? null;
                @endphp
                <div style="background:rgba(0,0,0,0.15);border:1px solid var(--glass-border);border-radius:10px;padding:15px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                        <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:600;">Uptime 24hs</span>
                        <span style="font-size:13px;font-weight:700;color:{{ ($stats['uptime'] ?? 0) >= 99 ? 'var(--success)' : 'var(--warning)' }};">
                            {{ $stats['uptime'] ?? 0 }}%
                        </span>
                    </div>
                    
                    <div style="height:60px;width:100%;">
                        <canvas id="chart-{{ $monitor->id }}" data-labels='@json($stats['labels'] ?? [])' data-values='@json($stats['data'] ?? [])'></canvas>
                    </div>
                </div>
                
                {{-- Last Check / Error --}}
                <div style="font-size:11px;color:var(--text-muted);display:flex;justify-content:space-between;">
                    <span>Última vez: {{ $monitor->last_checked_at ? $monitor->last_checked_at->diffForHumans() : 'Nunca' }}</span>
                    <span>Intervalo: {{ $monitor->interval_minutes }}m</span>
                </div>
                @if($monitor->status === 'down' && $monitor->last_error)
                <div style="font-size:11px;color:#f87171;background:rgba(239,68,68,0.1);padding:8px;border-radius:6px;border:1px solid rgba(239,68,68,0.2);">
                    <strong>Error:</strong> {{ \Illuminate\Support\Str::limit($monitor->last_error, 80) }}
                </div>
                @endif

                {{-- Actions --}}
                <div style="display:flex;gap:10px;margin-top:auto;padding-top:10px;border-top:1px solid var(--glass-border);">
                    <button wire:click="togglePause({{ $monitor->id }})" class="btn btn-ghost btn-sm" style="flex:1;">
                        @if($monitor->status === 'paused')
                            <i class="fa-solid fa-play"></i> Reanudar
                        @else
                            <i class="fa-solid fa-pause"></i> Pausar
                        @endif
                    </button>
                    <button wire:click="deleteMonitor({{ $monitor->id }})" onclick="return confirm('¿Seguro que deseas eliminar este monitor?')" class="btn btn-danger btn-sm" style="flex:1;">
                        <i class="fa-solid fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    @endif

    {{-- Create Modal --}}
    @if($showCreateModal)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.8);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="width:100%;max-width:400px;padding:28px;border-radius:16px;border:1px solid var(--glass-border);background:rgba(15,23,42,0.95);">
            <h3 style="font-size:18px;font-weight:700;margin:0 0 20px;">Añadir Monitor</h3>
            
            <form wire:submit.prevent="createMonitor">
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:6px;">Nombre (Ej: Mi Blog)</label>
                    <input type="text" wire:model="name" class="form-input" required style="width:100%;padding:10px;background:rgba(0,0,0,0.2);border:1px solid var(--glass-border);border-radius:8px;color:white;">
                    @error('name') <span style="color:var(--danger);font-size:11px;">{{ $message }}</span> @enderror
                </div>
                
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:6px;">Tipo de Servicio</label>
                    <select wire:model.live="type" class="form-input" style="width:100%;padding:10px;background:rgba(0,0,0,0.2);border:1px solid var(--glass-border);border-radius:8px;color:white;">
                        <option value="http" style="color:black;">Sitio Web (HTTP/HTTPS)</option>
                        <option value="docker" style="color:black;">Contenedor Docker</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:6px;">
                        @if($type === 'http') URL (Ej: https://miweb.com) @else Nombre del Contenedor (Ej: mysql_db) @endif
                    </label>
                    <input type="text" wire:model="target" class="form-input" required style="width:100%;padding:10px;background:rgba(0,0,0,0.2);border:1px solid var(--glass-border);border-radius:8px;color:white;">
                    @error('target') <span style="color:var(--danger);font-size:11px;">{{ $message }}</span> @enderror
                </div>
                
                <div class="form-group" style="margin-bottom:20px;">
                    <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:6px;">Comprobar cada (Minutos)</label>
                    <input type="number" wire:model="interval_minutes" class="form-input" required min="1" max="60" style="width:100%;padding:10px;background:rgba(0,0,0,0.2);border:1px solid var(--glass-border);border-radius:8px;color:white;">
                </div>
                
                <div style="display:flex;gap:10px;">
                    <button type="button" wire:click="$set('showCreateModal', false)" class="btn btn-ghost" style="flex:1;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="flex:1;">Guardar Monitor</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('livewire:navigated', function () {
            initCharts();
        });
        
        document.addEventListener('livewire:initialized', function () {
            initCharts();
            
            Livewire.hook('morph.updated', ({ component }) => {
                // Re-init charts if component updates
                setTimeout(initCharts, 50);
            });
        });

        function initCharts() {
            const canvases = document.querySelectorAll('canvas[id^="chart-"]');
            
            canvases.forEach(canvas => {
                if(Chart.getChart(canvas)) {
                    Chart.getChart(canvas).destroy();
                }
                
                const labels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
                const data = JSON.parse(canvas.getAttribute('data-values') || '[]');
                
                // Color green for responses, red for 0 (down)
                const colors = data.map(val => val > 0 ? '#10b981' : '#ef4444');
                
                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: colors,
                            borderRadius: 2,
                            barPercentage: 1,
                            categoryPercentage: 0.9,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let val = context.raw;
                                        return val === 0 ? 'DOWN' : val + ' ms';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { display: false },
                            y: { display: false, min: 0 }
                        },
                        animation: false
                    }
                });
            });
        }
    </script>
    @endpush
</div>
