<div wire:poll.5s="refreshMetrics">
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="fa-solid fa-gauge-high" style="color:var(--accent-light);"></i> Dashboard del Sistema</h1>
        </div>
        <div style="font-size:12px;color:var(--text-muted);">
            Actualización: <span style="color:var(--success);">En vivo</span>
        </div>
    </div>

    {{-- Resumen Rápido (Top Widgets) --}}
    <div class="stats-row" style="margin-bottom:24px;">
        <div class="glass-elevated" style="padding:20px;display:flex;align-items:center;gap:16px;">
            <div style="width:48px;height:48px;border-radius:12px;background:rgba(99,102,241,0.1);color:var(--accent-light);display:flex;align-items:center;justify-content:center;font-size:24px;">
                <i class="fa-solid fa-globe"></i>
            </div>
            <div>
                <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:600;">Mis Dominios</div>
                <div style="font-size:28px;font-weight:800;">{{ $domainCount }}</div>
            </div>
        </div>

        <div class="glass-elevated" style="padding:20px;display:flex;align-items:center;gap:16px;">
            <div style="width:48px;height:48px;border-radius:12px;background:rgba(39,201,63,0.1);color:var(--success);display:flex;align-items:center;justify-content:center;font-size:24px;">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:600;">Uptime</div>
                <div style="font-size:20px;font-weight:800;">{{ $metrics['uptime'] ?? 'N/A' }}</div>
            </div>
        </div>

        <div class="glass-elevated" style="padding:20px;display:flex;align-items:center;gap:16px;">
            <div style="width:48px;height:48px;border-radius:12px;background:rgba(255,193,7,0.1);color:var(--warning);display:flex;align-items:center;justify-content:center;font-size:24px;">
                <i class="fa-brands fa-linux"></i>
            </div>
            <div style="min-width:0;">
                <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:600;">Kernel</div>
                <div style="font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;" title="{{ $metrics['system']['kernel'] ?? 'Unknown' }}">{{ $metrics['system']['kernel'] ?? 'Unknown' }}</div>
            </div>
        </div>

        <div class="glass-elevated" style="padding:20px;display:flex;align-items:center;gap:16px;">
            <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.1);color:#10b981;display:flex;align-items:center;justify-content:center;font-size:24px;">
                <i class="fa-solid fa-server"></i>
            </div>
            <div style="min-width:0;">
                <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:600;">Hostname</div>
                <div style="font-size:14px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;">{{ $metrics['system']['hostname'] ?? 'Unknown' }}</div>
            </div>
        </div>

        <div class="glass-elevated" style="padding:20px;display:flex;align-items:center;gap:16px;">
            <div style="width:48px;height:48px;border-radius:12px;background:rgba(239,68,68,0.1);color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:24px;">
                <i class="fa-solid fa-microchip"></i>
            </div>
            <div style="min-width:0;">
                <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:600;">Sistema</div>
                <div style="font-size:12px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;" title="{{ $metrics['system']['os'] ?? 'Linux' }}">{{ $metrics['system']['os'] ?? 'Linux' }}</div>
            </div>
        </div>
    </div>


    {{-- Estado de Recursos (Gráficas en Vivo) --}}
    @if(auth()->user()?->isAdmin() || config('larapanel.modules.monitoring'))
    <h2 class="panel-title" style="margin-bottom:16px;border-bottom:1px solid var(--glass-border);padding-bottom:8px;">Uso de Recursos (En Vivo)</h2>
    <div class="lp-three-col" style="margin-bottom:24px;">
        
        {{-- CPU --}}
        <div class="glass lp-panel" style="display:flex;flex-direction:column;align-items:center;justify-content:center;position:relative;">
            <h4 class="panel-title" style="margin-bottom:16px;color:var(--text-muted);align-self:flex-start;">Procesador (CPU)</h4>
            <div style="position:relative;width:140px;height:140px;margin:0 auto;">
                <canvas id="cpuChart"></canvas>
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%, -50%);font-size:24px;font-weight:800;color:var(--text-primary);">
                    <span id="cpuValue">{{ $metrics['cpu'] ?? 0 }}</span>%
                </div>
            </div>
            <div style="margin-top:16px;font-size:12px;color:var(--text-secondary);text-align:center;">
                Carga: <span style="color:var(--accent-light);">{{ $metrics['loadavg'] ?? '0.00' }}</span><br>
                {{ $metrics['cpu_model'] ?? 'CPU' }}
            </div>
        </div>

        {{-- RAM --}}
        <div class="glass lp-panel" style="display:flex;flex-direction:column;align-items:center;justify-content:center;position:relative;">
            <h4 class="panel-title" style="margin-bottom:16px;color:var(--text-muted);align-self:flex-start;">Memoria RAM</h4>
            <div style="position:relative;width:140px;height:140px;margin:0 auto;">
                <canvas id="ramChart"></canvas>
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%, -50%);font-size:24px;font-weight:800;color:var(--text-primary);">
                    <span id="ramValue">{{ is_array($metrics['ram'] ?? null) ? ($metrics['ram']['usage'] ?? 0) : 0 }}</span>%
                </div>
            </div>
            <div style="margin-top:16px;font-size:12px;color:var(--text-secondary);text-align:center;">
                {{ \App\Services\MonitoringService::formatBytes(is_array($metrics['ram'] ?? null) ? ($metrics['ram']['used'] ?? 0) : 0) }} usados de {{ \App\Services\MonitoringService::formatBytes(is_array($metrics['ram'] ?? null) ? ($metrics['ram']['total'] ?? 0) : 0) }}
            </div>
        </div>

        {{-- Disk --}}
        <div class="glass lp-panel" style="display:flex;flex-direction:column;align-items:center;justify-content:center;position:relative;">
            <h4 class="panel-title" style="margin-bottom:16px;color:var(--text-muted);align-self:flex-start;">Almacenamiento (/)</h4>
            <div style="position:relative;width:140px;height:140px;margin:0 auto;">
                <canvas id="diskChart"></canvas>
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%, -50%);font-size:24px;font-weight:800;color:var(--text-primary);">
                    <span id="diskValue">{{ is_array($metrics['disk'] ?? null) ? ($metrics['disk']['usage'] ?? 0) : 0 }}</span>%
                </div>
            </div>
            <div style="margin-top:16px;font-size:12px;color:var(--text-secondary);text-align:center;">
                {{ \App\Services\MonitoringService::formatBytes(is_array($metrics['disk'] ?? null) ? ($metrics['disk']['used'] ?? 0) : 0) }} usados de {{ \App\Services\MonitoringService::formatBytes(is_array($metrics['disk'] ?? null) ? ($metrics['disk']['total'] ?? 0) : 0) }}
            </div>
        </div>
    </div>

    {{-- Historial de Recursos (Líneas) --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;margin-top:32px;border-bottom:1px solid var(--glass-border);padding-bottom:8px;">
        <h2 class="panel-title" style="margin:0;">Historial de Consumo</h2>
        <select wire:model.live="timeRange" class="form-select form-select-sm" style="width: auto; background: var(--glass-bg); color: var(--text-primary); border: 1px solid var(--glass-border);">
            <option value="1h">Última Hora</option>
            <option value="24h">Últimas 24 Horas</option>
            <option value="7d">Últimos 7 Días</option>
        </select>
    </div>
    <div class="glass lp-panel" style="margin-bottom:24px;height:300px;">
        <canvas id="historyChart"></canvas>
    </div>
    @endif

    {{-- Estado de Servicios --}}
    @if(auth()->user()?->isAdmin() && !empty($services))
    <h2 class="panel-title" style="margin-bottom:16px;border-bottom:1px solid var(--glass-border);padding-bottom:8px;">Estado de Servicios</h2>
    <div class="stats-row">
        @foreach($services as $name => $status)
            <div style="background:rgba(255,255,255,0.02);border:1px solid var(--glass-border);padding:16px;border-radius:8px;display:flex;align-items:center;justify-content:space-between;">
                <div style="font-size:14px;font-weight:600;color:var(--text-primary);text-transform:capitalize;">{{ $name }}</div>
                @if($status)
                    <div style="color:var(--success);font-size:12px;display:flex;align-items:center;gap:4px;font-weight:600;"><i class="fa-solid fa-circle" style="font-size:8px;"></i> Online</div>
                @else
                    <div style="color:var(--danger);font-size:12px;display:flex;align-items:center;gap:4px;font-weight:600;"><i class="fa-solid fa-circle" style="font-size:8px;"></i> Offline</div>
                @endif
            </div>
        @endforeach
    </div>
    @endif

</div>

@push('scripts')
<script>
document.addEventListener('livewire:initialized', () => {
    
    Chart.defaults.color = 'rgba(255, 255, 255, 0.6)';
    Chart.defaults.font.family = "'Inter', sans-serif";

    const commonOptions = {
        cutout: '80%',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { enabled: false }
        },
        animation: { animateRotate: false, animateScale: false }
    };

    const ctxCpu = document.getElementById('cpuChart')?.getContext('2d');
    const ctxRam = document.getElementById('ramChart')?.getContext('2d');
    const ctxDisk = document.getElementById('diskChart')?.getContext('2d');

    if(!ctxCpu) return;

    let cpuChart = new Chart(ctxCpu, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [{{ $metrics['cpu'] ?? 0 }}, {{ 100 - ($metrics['cpu'] ?? 0) }}],
                backgroundColor: ['#6366f1', 'rgba(255,255,255,0.05)'],
                borderWidth: 0,
                borderRadius: [4, 0]
            }]
        },
        options: commonOptions
    });

        let ramChart = new Chart(ctxRam, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [{{ is_array($metrics['ram'] ?? null) ? ($metrics['ram']['usage'] ?? 0) : 0 }}, {{ 100 - (is_array($metrics['ram'] ?? null) ? ($metrics['ram']['usage'] ?? 0) : 0) }}],
                backgroundColor: ['#27c93f', 'rgba(255,255,255,0.05)'],
                borderWidth: 0,
                borderRadius: [4, 0]
            }]
        },
        options: commonOptions
    });

    let diskChart = new Chart(ctxDisk, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [{{ is_array($metrics['disk'] ?? null) ? ($metrics['disk']['usage'] ?? 0) : 0 }}, {{ 100 - (is_array($metrics['disk'] ?? null) ? ($metrics['disk']['usage'] ?? 0) : 0) }}],
                backgroundColor: ['#ffc107', 'rgba(255,255,255,0.05)'],
                borderWidth: 0,
                borderRadius: [4, 0]
            }]
        },
        options: commonOptions
    });

    const ctxHistory = document.getElementById('historyChart')?.getContext('2d');
    let historyChart = null;

    if(ctxHistory) {
        let rawHistory = @json($history);
        
        historyChart = new Chart(ctxHistory, {
            type: 'line',
            data: {
                labels: rawHistory.map(h => h.time),
                datasets: [
                    {
                        label: 'CPU %',
                        data: rawHistory.map(h => h.cpu),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHitRadius: 10
                    },
                    {
                        label: 'RAM %',
                        data: rawHistory.map(h => h.ram),
                        borderColor: '#27c93f',
                        backgroundColor: 'rgba(39, 201, 63, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHitRadius: 10
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { callback: function(value) { return value + '%' } }
                    },
                    x: {
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                    }
                }
            }
        });
    }

    Livewire.on('history-updated', (data) => {
        let newHistory = data[0];
        if(!newHistory || !historyChart) return;

        historyChart.data.labels = newHistory.map(h => h.time);
        historyChart.data.datasets[0].data = newHistory.map(h => h.cpu);
        historyChart.data.datasets[1].data = newHistory.map(h => h.ram);
        historyChart.update();
    });

    Livewire.on('snapshot-updated', (data) => {
        let metrics = data[0];
        if(!metrics) return;

        // CPU Update
        let cVal = parseFloat(metrics.cpu);
        cpuChart.data.datasets[0].data = [cVal, 100 - cVal];
        let cpuColor = cVal > 85 ? '#ff5f56' : '#6366f1';
        cpuChart.data.datasets[0].backgroundColor[0] = cpuColor;
        cpuChart.update('none');
        document.getElementById('cpuValue').innerText = Math.round(cVal);
        document.getElementById('cpuValue').style.color = cpuColor;

        // RAM Update
        let rVal = metrics.ram && metrics.ram.usage !== undefined ? parseFloat(metrics.ram.usage) : 0;
        ramChart.data.datasets[0].data = [rVal, 100 - rVal];
        let ramColor = rVal > 85 ? '#ff5f56' : '#27c93f';
        ramChart.data.datasets[0].backgroundColor[0] = ramColor;
        ramChart.update('none');
        document.getElementById('ramValue').innerText = Math.round(rVal);
        document.getElementById('ramValue').style.color = ramColor;

        // Disk Update
        let dVal = metrics.disk && metrics.disk.usage !== undefined ? parseFloat(metrics.disk.usage) : 0;
        diskChart.data.datasets[0].data = [dVal, 100 - dVal];
        let diskColor = dVal > 90 ? '#ff5f56' : '#ffc107';
        diskChart.data.datasets[0].backgroundColor[0] = diskColor;
        diskChart.update('none');
        document.getElementById('diskValue').innerText = Math.round(dVal);
        document.getElementById('diskValue').style.color = diskColor;
    });
});
</script>
@endpush
