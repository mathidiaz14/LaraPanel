<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-heart-pulse" style="color:var(--accent-light);margin-right:10px;"></i>
                Salud del Servidor
            </h1>
            <p class="page-subtitle">Puntuación compuesta a partir de CPU, RAM, disco, swap, servicios críticos, SSL y uptime.</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <button wire:click="load" class="btn btn-ghost btn-sm" wire:loading.attr="disabled">
                <span wire:loading.remove><i class="fa-solid fa-rotate"></i> Recalcular</span>
                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Calculando...</span>
            </button>
            <button wire:click="saveSnapshot" class="btn btn-primary btn-sm"
                    title="Guarda un snapshot del estado actual">
                <i class="fa-solid fa-camera"></i> Guardar snapshot
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

    @php
        $score      = $scoreData['score'] ?? 0;
        $grade      = $scoreData['grade'] ?? 'F';
        $components = $scoreData['components'] ?? [];

        $color = match (true) {
            $score >= 90 => 'var(--success)',
            $score >= 70 => 'var(--warning)',
            default      => 'var(--danger)',
        };
        $radius       = 70;
        $circumference = 2 * M_PI * $radius;
        $offset       = $circumference * (1 - ($score / 100));
    @endphp

    <div class="stats-row" style="margin-bottom:20px;align-items:stretch;">

        {{-- Score gauge --}}
        <div class="glass lp-panel" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;">
            <div style="position:relative;width:170px;height:170px;">
                <svg width="170" height="170" viewBox="0 0 170 170">
                    <circle cx="85" cy="85" r="{{ $radius }}" fill="none"
                            stroke="var(--glass-bg)" stroke-width="14" />
                    <circle cx="85" cy="85" r="{{ $radius }}" fill="none"
                            stroke="{{ $color }}" stroke-width="14" stroke-linecap="round"
                            stroke-dasharray="{{ $circumference }}"
                            stroke-dashoffset="{{ $offset }}" transform="rotate(-90 85 85)"
                            style="transition:stroke-dashoffset 0.8s ease, stroke 0.4s ease;" />
                </svg>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <div style="font-size:42px;font-weight:900;line-height:1;color:var(--text-primary);">{{ $score }}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">/ 100</div>
                </div>
            </div>
            <span class="badge" @style([
                'font-size:14px;padding:5px 14px;',
                'background:' . $color => true,
                'color:#0c0f16;' => true,
            ])>
                Grado: <strong>{{ $grade }}</strong>
            </span>
            @if(!empty($scoreData['computed_at']))
                <div style="font-size:10px;color:var(--text-muted);">Calculado: {{ $scoreData['computed_at'] }}</div>
            @endif
        </div>

        {{-- Component breakdown --}}
        <div class="glass lp-panel" style="padding:0;overflow:hidden;display:flex;flex-direction:column;">
            <div style="padding:var(--sp-4) var(--sp-6);border-bottom:1px solid var(--glass-border);">
                <strong style="font-size:13px;"><i class="fa-solid fa-list-check" style="margin-right:6px;color:var(--accent-light);"></i> Desglose</strong>
            </div>
            <div class="table-responsive" style="flex:1;">
                <table class="lp-table" style="margin:0;">
                    <thead>
                        <tr>
                            <th>Factor</th>
                            <th>Valor</th>
                            <th style="width:140px;">Puntuación</th>
                            <th>Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($components as $comp)
                            @php
                                $pts = $comp['points'] ?? 0;
                                $c = $pts >= 90 ? 'var(--success)' : ($pts >= 60 ? 'var(--warning)' : 'var(--danger)');
                            @endphp
                            <tr>
                                <td style="font-weight:600;">
                                    {{ $comp['label'] ?? $comp['key'] }}
                                    <span style="color:var(--text-muted);font-weight:400;font-size:10px;">{{ $comp['weight'] ?? 0 }} pts</span>
                                </td>
                                <td style="font-family:monospace;font-size:12px;color:var(--text-muted);">{{ $comp['value'] ?? '—' }}</td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-weight:700;font-size:12px;color:{{ $c }};min-width:26px;">{{ $pts }}</span>
                                        <div style="flex:1;height:5px;background:var(--glass-bg);border-radius:3px;overflow:hidden;">
                                            <div style="width:{{ $pts }}%;height:100%;background:{{ $c }};border-radius:3px;transition:width 0.5s;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-size:11px;color:var(--text-muted);">{!! $comp['note'] ?? '' !!}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align:center;padding:32px;color:var(--text-muted);">
                                    No hay datos de desglose disponibles.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Trend chart --}}
    <div class="glass lp-panel" style="padding:var(--sp-5) var(--sp-6);margin-bottom:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--sp-4);">
            <strong style="font-size:13px;"><i class="fa-solid fa-chart-line" style="margin-right:6px;color:var(--accent-light);"></i> Tendencia 24h (CPU / RAM / Disco)</strong>
            <span style="font-size:11px;color:var(--text-muted);">Promedio por hora</span>
        </div>
        <div style="height:260px;width:100%;">
            <canvas id="health-trend-chart"
                    data-labels='@json($trend['labels'] ?? [])'
                    data-cpu='@json($trend['cpu'] ?? [])'
                    data-ram='@json($trend['ram'] ?? [])'
                    data-disk='@json($trend['disk'] ?? [])'></canvas>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('livewire:navigated', function () {
            initHealthChart();
        });

        document.addEventListener('livewire:initialized', function () {
            initHealthChart();

            Livewire.hook('morph.updated', ({ component }) => {
                if (component) {
                    setTimeout(initHealthChart, 50);
                }
            });
        });

        function initHealthChart() {
            const canvas = document.getElementById('health-trend-chart');
            if (!canvas) return;

            if (Chart.getChart(canvas)) {
                Chart.getChart(canvas).destroy();
            }

            const labels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
            const cpu   = JSON.parse(canvas.getAttribute('data-cpu') || '[]');
            const ram   = JSON.parse(canvas.getAttribute('data-ram') || '[]');
            const disk  = JSON.parse(canvas.getAttribute('data-disk') || '[]');

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'CPU',
                            data: cpu,
                            borderColor: '#38bdf8',
                            backgroundColor: 'rgba(56,189,248,0.08)',
                            tension: 0.35,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHitRadius: 8,
                        },
                        {
                            label: 'RAM',
                            data: ram,
                            borderColor: '#a78bfa',
                            backgroundColor: 'rgba(167,139,250,0.08)',
                            tension: 0.35,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHitRadius: 8,
                        },
                        {
                            label: 'Disco',
                            data: disk,
                            borderColor: '#34d399',
                            backgroundColor: 'rgba(52,211,153,0.08)',
                            tension: 0.35,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHitRadius: 8,
                        },
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: {
                            ticks: { color: 'rgba(255,255,255,0.4)', maxTicksLimit: 12, font: { size: 10 } },
                            grid: { color: 'rgba(255,255,255,0.04)' },
                        },
                        y: {
                            min: 0,
                            max: 100,
                            ticks: { color: 'rgba(255,255,255,0.4)', font: { size: 10 }, callback: v => v + '%' },
                            grid: { color: 'rgba(255,255,255,0.06)' },
                        }
                    },
                    plugins: {
                        legend: {
                            labels: { color: 'rgba(255,255,255,0.7)', font: { size: 11 }, boxWidth: 10 },
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => ctx.dataset.label + ': ' + ctx.raw + '%',
                            }
                        }
                    },
                    animation: false,
                }
            });
        }
    </script>
    @endpush
</div>