<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="fa-solid fa-database" style="color:var(--accent-light);"></i> Gestor de Caché</h1>
            <p class="page-subtitle">Estadísticas en tiempo real y gestión de Redis y Memcached del servidor.</p>
        </div>
        <button wire:click="refresh" wire:loading.attr="disabled" class="btn btn-primary">
            <i class="fa-solid fa-rotate"></i> Refrescar
        </button>
    </div>

    {{-- Alerts --}}
    @if($successMessage)
        <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    @php
        // Redis computed helpers
        $rInfo  = $redis['info'] ?? [];
        $rMem   = $rInfo['memory'] ?? [];
        $rUsed  = (int) ($rMem['used_memory'] ?? 0);
        $rMax   = (int) ($rMem['maxmemory'] ?? 0);
        $rMemPct = $rMax > 0 ? round($rUsed / $rMax * 100, 1) : null;

        $fmtB = function ($b) {
            $b = (float) $b;
            if ($b <= 0) return '0 B';
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $i = min((int) floor(log($b, 1024)), count($units) - 1);
            return round($b / pow(1024, $i), 1) . ' ' . $units[$i];
        };

        $upFmt = function ($sec) {
            $sec = (int) $sec;
            if ($sec <= 0) return '—';
            $out = [];
            $d = intdiv($sec, 86400); if ($d > 0) $out[] = $d . 'd';
            $h = intdiv($sec % 86400, 3600); if ($h > 0) $out[] = $h . 'h';
            $m = intdiv($sec % 3600, 60); if ($m > 0) $out[] = $m . 'm';
            if ($out === []) $out[] = $sec . 's';
            return implode(' ', array_slice($out, 0, 3));
        };

        $rServer  = $rInfo['server'] ?? [];
        $rClients = $rInfo['clients'] ?? [];
        $rStats   = $rInfo['stats'] ?? [];
        $rKeyspace = $rInfo['keyspace'] ?? [];
        $redisConnected = (bool) ($redis['connected'] ?? false);

        // Memcached computed helpers
        $mStats = $memcached['stats'] ?? [];
        $mAvailable = (bool) ($memcached['available'] ?? false);
        $mUsedBytes = (int) ($mStats['bytes'] ?? 0);
        $mLimitBytes = (int) ($mStats['limit_maxbytes'] ?? 0);
        $mPct = $mLimitBytes > 0 ? round($mUsedBytes / $mLimitBytes * 100, 1) : null;
    @endphp

    <div class="lp-two-col">

        {{-- ── Redis Panel ─────────────────────────────────────────── --}}
        <div class="glass lp-panel">
            <h2 class="panel-title" style="display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-cube" style="color:var(--danger);"></i>
                Redis
                @if($redisConnected)
                    <span class="badge badge-success" style="font-size:10px;padding:2px 8px;color:var(--success);background:rgba(16,185,129,0.12);">CONECTADO</span>
                @else
                    <span class="badge badge-danger" style="font-size:10px;padding:2px 8px;color:var(--danger);background:rgba(239,68,68,0.12);">SIN CONEXIÓN</span>
                @endif
            </h2>

            @if(!$redisConnected)
                <div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);border-radius:var(--radius-sm);padding:12px 14px;display:flex;align-items:center;gap:10px;">
                    <i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);"></i>
                    <p style="font-size:12px;color:var(--text-secondary);margin:0;">El servidor Redis no está respondiendo al PING. Verifica que el servicio esté activo.</p>
                </div>
            @else
                {{-- Stat cards --}}
                <div class="stats-row" style="margin-bottom:16px;">
                    <div class="glass" style="padding:12px;text-align:center;">
                        <div style="font-size:18px;font-weight:800;color:var(--text-primary);">{{ $rServer['redis_version'] ?? '—' }}</div>
                        <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Versión</div>
                    </div>
                    <div class="glass" style="padding:12px;text-align:center;">
                        <div style="font-size:13px;font-weight:700;color:var(--accent-light);">{{ $upFmt($rServer['uptime_in_seconds'] ?? 0) }}</div>
                        <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Uptime</div>
                    </div>
                    <div class="glass" style="padding:12px;text-align:center;">
                        <div style="font-size:18px;font-weight:800;color:var(--info);">{{ $rClients['connected_clients'] ?? 0 }}</div>
                        <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Clientes</div>
                    </div>
                    <div class="glass" style="padding:12px;text-align:center;">
                        <div style="font-size:18px;font-weight:800;color:var(--success);">{{ $redis['dbsize'] ?? 0 }}</div>
                        <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Claves</div>
                    </div>
                </div>

                {{-- Memory bar --}}
                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label" style="font-size:11px;">Memoria</label>
                    @if($rMemPct !== null)
                        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-bottom:4px;">
                            <span>{{ $fmtB($rUsed) }} de {{ $fmtB($rMax) }}</span>
                            <span style="font-weight:700;color:{{ $rMemPct > 85 ? 'var(--danger)' : 'var(--success)' }};">{{ $rMemPct }}%</span>
                        </div>
                        <div style="height:8px;background:rgba(255,255,255,0.08);border-radius:6px;overflow:hidden;">
                            <div style="height:100%;width:{{ min(100, $rMemPct) }}%;background:{{ $rMemPct > 85 ? 'var(--danger)' : 'var(--success)' }};transition:width .3s;"></div>
                        </div>
                    @else
                        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);">
                            <span>Usado: <strong style="color:var(--success);">{{ $fmtB($rUsed) }}</strong></span>
                            <span>Sin límite configurado (maxmemory=0)</span>
                        </div>
                    @endif
                </div>

                {{-- Operations stats --}}
                <div class="table-responsive">
                <table class="lp-table" style="margin:0 0 16px;">
                    <thead>
                        <tr><th style="text-align:left;">Operaciones</th><th style="text-align:right;">Valor</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align:left;">Comandos procesados</td>
                            <td style="text-align:right;font-family:monospace;">{{ number_format((int) ($rStats['total_commands_processed'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;">Conexiones recibidas</td>
                            <td style="text-align:right;font-family:monospace;">{{ number_format((int) ($rStats['total_connections_received'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;">Aciertos de clave</td>
                            <td style="text-align:right;font-family:monospace;">{{ number_format((int) ($rStats['keyspace_hits'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;">Fallos de clave</td>
                            <td style="text-align:right;font-family:monospace;">{{ number_format((int) ($rStats['keyspace_misses'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;">Fragmentación</td>
                            <td style="text-align:right;font-family:monospace;">{{ $rMem['mem_fragmentation_ratio'] ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>

                {{-- Keyspace --}}
                @if($rKeyspace)
                    <div style="margin-bottom:16px;">
                        <label class="form-label" style="font-size:11px;">Keyspace</label>
                        @foreach($rKeyspace as $db => $detail)
                            <div style="font-size:12px;color:var(--text-secondary);background:rgba(255,255,255,0.03);border:1px solid var(--glass-border);border-radius:var(--radius-sm);padding:6px 10px;margin-bottom:4px;font-family:monospace;">
                                {{ $db }}: {{ $detail }}
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Keys list --}}
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <label class="form-label" style="font-size:11px;margin:0;">Claves ({{ $totalKeys }})</label>
                    <button wire:click="flushRedis" onclick="return confirm('¿Vaciar TODAS las claves de Redis? Esta acción no se puede deshacer.')" class="btn btn-danger btn-sm" wire:loading.attr="disabled">
                        <i class="fa-solid fa-broom"></i> FLUSH
                    </button>
                </div>

                @if(count($pageKeys) === 0)
                    <p style="font-size:12px;color:var(--text-muted);margin:0;">No hay claves almacenadas.</p>
                @else
                    <div class="table-responsive">
                    <table class="lp-table" style="margin:0 0 8px;">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th style="text-align:left;">Clave</th>
                                <th style="width:44px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pageKeys as $i => $key)
                                <tr>
                                    <td style="color:var(--text-muted);">{{ $redisStart + $i }}</td>
                                    <td style="text-align:left;font-family:monospace;font-size:12px;word-break:break-all;">{{ $key }}</td>
                                    <td>
                                        <button wire:click="deleteKey('{{ addslashes($key) }}')" class="btn btn-ghost btn-sm btn-icon" title="Eliminar clave" onclick="return confirm('¿Eliminar esta clave?')">
                                            <i class="fa-solid fa-trash" style="color:var(--danger);font-size:12px;"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:11px;color:var(--text-muted);">Mostrando {{ $redisStart }}–{{ $redisEnd }} de {{ $totalKeys }}</span>
                        <div style="display:flex;gap:6px;">
                            <button wire:click="prevRedisPage" class="btn btn-ghost btn-sm" @disabled(!$hasPrev)>
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>
                            <button wire:click="nextRedisPage" class="btn btn-ghost btn-sm" @disabled(!$hasNext)>
                                <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        {{-- ── Memcached Panel ─────────────────────────────────────── --}}
        <div class="glass lp-panel">
            <h2 class="panel-title" style="display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-bolt" style="color:var(--warning);"></i>
                Memcached
                @if($mAvailable)
                    <span class="badge badge-success" style="font-size:10px;padding:2px 8px;color:var(--success);background:rgba(16,185,129,0.12);">CONECTADO</span>
                @else
                    <span class="badge badge-warning" style="font-size:10px;padding:2px 8px;color:var(--warning);background:rgba(245,158,11,0.12);">NO DISPONIBLE</span>
                @endif
            </h2>

            @if(!$mAvailable)
                <div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);border-radius:var(--radius-sm);padding:12px 14px;display:flex;align-items:center;gap:10px;">
                    <i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);flex-shrink:0;"></i>
                    <p style="font-size:12px;color:var(--text-secondary);margin:0;">{{ $memcached['message'] ?? 'No hay estadísticas disponibles.' }}</p>
                </div>
            @else
                {{-- Stat cards --}}
                <div class="stats-row" style="margin-bottom:16px;">
                    <div class="glass" style="padding:12px;text-align:center;">
                        <div style="font-size:13px;font-weight:700;color:var(--accent-light);">{{ $upFmt($mStats['uptime'] ?? 0) }}</div>
                        <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Uptime</div>
                    </div>
                    <div class="glass" style="padding:12px;text-align:center;">
                        <div style="font-size:18px;font-weight:800;color:var(--info);">{{ $mStats['curr_connections'] ?? 0 }}</div>
                        <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Conexiones</div>
                    </div>
                    <div class="glass" style="padding:12px;text-align:center;">
                        <div style="font-size:18px;font-weight:800;color:var(--success);">{{ $mStats['curr_items'] ?? 0 }}</div>
                        <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Items</div>
                    </div>
                    <div class="glass" style="padding:12px;text-align:center;">
                        <div style="font-size:15px;font-weight:800;color:var(--warning);">{{ $fmtB($mUsedBytes) }}</div>
                        <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">En uso</div>
                    </div>
                </div>

                {{-- Memory bar --}}
                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label" style="font-size:11px;">Memoria</label>
                    @if($mPct !== null)
                        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-bottom:4px;">
                            <span>{{ $fmtB($mUsedBytes) }} de {{ $fmtB($mLimitBytes) }}</span>
                            <span style="font-weight:700;color:{{ $mPct > 85 ? 'var(--danger)' : 'var(--success)' }};">{{ $mPct }}%</span>
                        </div>
                        <div style="height:8px;background:rgba(255,255,255,0.08);border-radius:6px;overflow:hidden;">
                            <div style="height:100%;width:{{ min(100, $mPct) }}%;background:{{ $mPct > 85 ? 'var(--danger)' : 'var(--success)' }};transition:width .3s;"></div>
                        </div>
                    @else
                        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);">
                            <span>Usado: <strong style="color:var(--success);">{{ $fmtB($mUsedBytes) }}</strong></span>
                            <span>Sin límite configurado</span>
                        </div>
                    @endif
                </div>

                {{-- Stats table --}}
                <div class="table-responsive">
                <table class="lp-table" style="margin:0;">
                    <thead>
                        <tr><th style="text-align:left;">Métrica</th><th style="text-align:right;">Valor</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align:left;">Comandos GET</td>
                            <td style="text-align:right;font-family:monospace;">{{ number_format((int) ($mStats['cmd_get'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;">Comandos SET</td>
                            <td style="text-align:right;font-family:monospace;">{{ number_format((int) ($mStats['cmd_set'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;">GET aciertos</td>
                            <td style="text-align:right;font-family:monospace;">{{ number_format((int) ($mStats['get_hits'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;">GET fallos</td>
                            <td style="text-align:right;font-family:monospace;">{{ number_format((int) ($mStats['get_misses'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;">Evicciones</td>
                            <td style="text-align:right;font-family:monospace;">{{ number_format((int) ($mStats['evictions'] ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;">Expulsiones</td>
                            <td style="text-align:right;font-family:monospace;">{{ number_format((int) ($mStats['reclaimed'] ?? 0)) }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>

                <div style="margin-top:16px;background:rgba(146,64,14,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:var(--radius-sm);padding:10px 12px;display:flex;align-items:center;gap:10px;">
                    <i class="fa-solid fa-terminal" style="color:var(--warning);flex-shrink:0;"></i>
                    <p style="font-size:12px;color:var(--text-secondary);margin:0;">
                        El vaciado de Memcached requiere <code class="badge" style="font-size:11px;">telnet 127.0.0.1 11211</code> y el comando <code class="badge" style="font-size:11px;">flush_all</code>. No se ejecuta desde el panel por seguridad.
                    </p>
                </div>
            @endif
        </div>

    </div>
</div>