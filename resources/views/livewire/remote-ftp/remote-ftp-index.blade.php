<div>
    @php
        $fmtBytes = function($b) {
            if (!$b) return '0 B';
            $units = ['B','KB','MB','GB','TB'];
            $i = 0;
            $v = (float) $b;
            while ($v >= 1024 && $i < count($units) - 1) { $v /= 1024; $i++; }
            return round($v, 1) . ' ' . $units[$i];
        };
    @endphp

    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">FTP Remoto</h1>
            <p class="page-subtitle">
                Conéctese a un servidor FTP/FTPS externo, explore sus carpetas y copie archivos
                o árboles enteros directamente hacia este servidor (sin pasar por su equipo).
            </p>
        </div>
    </div>

    {{-- Alerts --}}
    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start;">

        {{-- Left: connection management --}}
        <div style="display:flex;flex-direction:column;gap:20px;">

            {{-- Saved connections --}}
            <div class="glass lp-panel">
                <h2 class="panel-title">
                    <i class="fa-solid fa-plug" style="color:var(--accent-light);margin-right:8px;"></i>
                    Conexiones Guardadas
                </h2>

                @if($connections->isEmpty())
                <div style="text-align:center;padding:30px 20px;color:var(--text-secondary);font-size:12px;">
                    <i class="fa-solid fa-cloud-arrow-down" style="font-size:32px;opacity:0.25;margin-bottom:10px;display:block;"></i>
                    No hay conexiones remotas guardadas.
                </div>
                @else
                <div style="display:flex;flex-direction:column;gap:8px;">
                    @foreach($connections as $conn)
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;background:rgba(255,255,255,0.04);border:1px solid var(--glass-border);border-radius:10px;padding:10px 12px;">
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <strong style="font-size:13px;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $conn->name }}</strong>
                                <span class="badge {{ $conn->protocol === 'ftps' ? 'badge-success' : 'badge-secondary' }}" style="font-size:10px;">{{ strtoupper($conn->protocol) }}</span>
                            </div>
                            <div style="font-size:11px;color:var(--text-muted);font-family:monospace;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                {{ $conn->username }}@{{ $conn->host }}:{{ $conn->port }}
                            </div>
                        </div>
                        <div style="display:inline-flex;gap:6px;flex-shrink:0;">
                            @if($activeConnectionId === $conn->id)
                            <span class="badge badge-success" style="font-size:10px;">Conectado</span>
                            @else
                            <button wire:click="connectTo({{ $conn->id }})" class="btn btn-ghost btn-sm" title="Conectar">
                                <i class="fa-solid fa-right-to-bracket" style="color:var(--accent-light);"></i>
                            </button>
                            @endif
                            <button wire:click="deleteConnection({{ $conn->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar esta conexión remota?')" title="Eliminar">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- New connection form --}}
            <div class="glass lp-panel">
                <h2 class="panel-title">
                    <i class="fa-solid fa-circle-plus" style="color:var(--success);margin-right:8px;"></i>
                    Nueva Conexión
                </h2>

                <form wire:submit.prevent="saveConnection">
                    <div class="form-group">
                        <label class="form-label">Nombre <span style="color:var(--text-muted);font-weight:400;">— solo etiqueta</span></label>
                        <input type="text" wire:model="name" class="form-input" placeholder="ej. Servidor prod cPanel">
                        @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Host / IP</label>
                        <input type="text" wire:model="host" class="form-input" placeholder="server.example.com">
                        @error('host') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div style="display:grid;grid-template-columns:3fr 1fr 1.2fr;gap:10px;">
                        <div class="form-group">
                            <label class="form-label">Usuario</label>
                            <input type="text" wire:model="username" class="form-input" placeholder="user@dominio">
                            @error('username') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">Puerto</label>
                            <input type="number" wire:model="port" class="form-input" min="1" max="65535" placeholder="21">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Protocolo</label>
                            <select wire:model="protocol" class="form-input">
                                <option value="ftp">FTP</option>
                                <option value="ftps">FTPS (TLS)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contraseña <span style="color:var(--text-muted);font-weight:400;">— opcional si ya está guardada</span></label>
                        <input type="password" wire:model="password" class="form-input" placeholder="Contraseña del servidor remoto">
                        @error('password') <div class="form-error">{{ $message }}</div> @enderror
                    </div>

                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                        <input type="checkbox" id="rf-passive" wire:model="passive" style="width:17px;height:17px;accent-color:var(--accent-light);">
                        <label for="rf-passive" class="form-label" style="margin:0;cursor:pointer;">Modo pasivo</label>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Carpeta inicial <span style="color:var(--text-muted);font-weight:400;">— opcional</span></label>
                        <input type="text" wire:model="initialPath" class="form-input" placeholder="/ o subcarpeta del servidor remoto">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                        <i class="fa-solid fa-plus-circle"></i> Guardar y Conectar
                    </button>

                    @if(!count($connections))
                    <div style="margin-top:10px;">
                        <button type="button" wire:click="connectTo(null)" class="btn btn-ghost" style="width:100%;justify-content:center;">
                            <i class="fa-solid fa-bolt"></i> Conectar sin guardar
                        </button>
                    </div>
                    @endif
                </form>
            </div>
        </div>

        {{-- Right: remote explorer --}}
        <div class="glass lp-panel">
            <h2 class="panel-title">
                <i class="fa-solid fa-folder-tree" style="color:var(--accent-light);margin-right:8px;"></i>
                Explorador Remoto
                @if($connected)
                <span class="badge badge-success" style="font-size:10px;float:right;margin-top:2px;">Conectado</span>
                @else
                <span class="badge badge-secondary" style="font-size:10px;float:right;margin-top:2px;">Sin conexión</span>
                @endif
            </h2>

            @if(!$connected)
            <div style="text-align:center;padding:60px 20px;color:var(--text-secondary);">
                <i class="fa-solid fa-plug-circle-xmark" style="font-size:42px;opacity:0.25;margin-bottom:14px;display:block;"></i>
                Conectese a un servidor remoto para explorar sus archivos.
            </div>
            @else
            {{-- Path bar --}}
            <div style="display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.05);border:1px solid var(--glass-border);border-radius:10px;padding:8px 12px;margin-bottom:14px;">
                <button wire:click="goUp" class="btn btn-ghost btn-sm" title="Subir" {{ $remoteCwd === '/' ? 'disabled' : '' }}>
                    <i class="fa-solid fa-arrow-up"></i>
                </button>
                <button wire:click="refreshListing" class="btn btn-ghost btn-sm" title="Refrescar">
                    <i class="fa-solid fa-rotate"></i>
                </button>
                <code style="font-size:12px;color:var(--accent-light);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:right;">{{ $remoteCwd }}</code>
                <button wire:click="disconnect" class="btn btn-danger btn-sm" title="Cerrar sesión">
                    <i class="fa-solid fa-power-off"></i>
                </button>
            </div>

            {{-- Listing --}}
            @if(empty($listing))
            <div style="text-align:center;padding:40px 20px;color:var(--text-secondary);font-size:12px;">
                <i class="fa-solid fa-folder-open" style="font-size:32px;opacity:0.25;margin-bottom:10px;display:block;"></i>
                Carpeta vacía o sin permiso de listado.
            </div>
            @else
            <div class="table-responsive">
                <table class="lp-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th style="text-align:right;">Tamaño</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $fmt = function($b) {
                                if (!$b) return '';
                                $units = ['B','KB','MB','GB','TB'];
                                $i = 0;
                                $v = (float) $b;
                                while ($v >= 1024 && $i < count($units) - 1) { $v /= 1024; $i++; }
                                return round($v, 2) . ' ' . $units[$i];
                            };
                        @endphp
                        @foreach($listing as $entry)
                        @php
                            $path = rtrim($remoteCwd, '/') . '/' . $entry['name'];
                            $pathB64 = base64_encode($path);
                        @endphp
                        <tr>
                            <td>
                                @if($entry['type'] === 'dir')
                                <button wire:click="openRemote('{{ $pathB64 }}')" class="btn" style="border:none;background:none;padding:0;font-size:13px;font-weight:600;color:var(--accent-light);text-align:left;">
                                    <i class="fa-solid fa-folder" style="margin-right:8px;"></i>{{ $entry['name'] }}
                                </button>
                                @elseif($entry['type'] === 'link')
                                <span style="font-size:13px;color:var(--warning);"><i class="fa-solid fa-link" style="margin-right:8px;"></i>{{ $entry['name'] }}</span>
                                @else
                                <span style="font-size:13px;color:var(--text-primary);"><i class="fa-regular fa-file" style="margin-right:8px;color:var(--text-muted);"></i>{{ $entry['name'] }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $entry['type'] === 'dir' ? 'badge-secondary' : ($entry['type'] === 'file' ? 'badge-info' : 'badge-warning') }}" style="font-size:10px;">
                                    {{ $entry['type'] === 'dir' ? 'Carpeta' : ($entry['type'] === 'file' ? 'Archivo' : 'Enlace') }}
                                </span>
                            </td>
                            <td style="text-align:right;font-size:12px;color:var(--text-secondary);">{{ $entry['type'] === 'file' ? $fmt($entry['size']) : '—' }}</td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:6px;">
                                    @if($entry['type'] === 'file')
                                    <button wire:click="askCopy('{{ $pathB64 }}','file')" class="btn btn-secondary btn-sm" title="Copiar archivo a este servidor">
                                        <i class="fa-solid fa-arrow-down-to-line" style="color:var(--success);"></i> Copiar
                                    </button>
                                    @elseif($entry['type'] === 'dir')
                                    <button wire:click="askCopy('{{ $pathB64 }}','dir')" class="btn btn-secondary btn-sm" title="Copiar carpeta completa (en background)">
                                        <i class="fa-solid fa-arrow-down-to-bracket" style="color:var(--accent-light);"></i> Copiar
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
            @endif
        </div>

    </div>

    {{-- Background jobs --}}
    <div class="glass lp-panel" wire:poll.2s="refreshJobs" style="margin-top:20px;">
        <h2 class="panel-title">
            <i class="fa-solid fa-list-check" style="color:var(--accent-light);margin-right:8px;"></i>
            Copias en Background
        </h2>

        @if($selectedJob)
        @php $selJob = collect($jobs)->firstWhere('id', $selectedJob); @endphp
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <code style="font-size:12px;color:var(--accent-light);">{{ $selectedJob }}</code>
            <div style="display:inline-flex;gap:6px;">
                <button wire:click="showJob('{{ $selectedJob }}')" class="btn btn-ghost btn-sm">
                    <i class="fa-solid fa-rotate"></i> Refrescar log
                </button>
                <button wire:click="deleteJob('{{ $selectedJob }}')" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este trabajo?')" title="Eliminar trabajo">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
        @if($selJob && $selJob['percent'] !== null)
        @php
            $fillClass = $selJob['status'] === 'done' ? 'success' : ($selJob['status'] === 'failed' || $selJob['status'] === 'stopped' ? 'danger' : 'accent');
        @endphp
        <div style="margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-secondary);margin-bottom:6px;">
                <span>
                    Copiado:
                    <b style="color:var(--text-primary);">{{ $fmtBytes($selJob['transferred_bytes']) }}</b>
                    de
                    <b style="color:var(--text-primary);">{{ $fmtBytes($selJob['total_bytes']) }}</b>
                </span>
                <span><b style="color:var(--accent-light);">{{ $selJob['percent'] }}%</b></span>
            </div>
            <div class="progress-bar" style="height:8px;">
                <div class="progress-fill {{ $fillClass }}" style="width:{{ $selJob['percent'] }}%;"></div>
            </div>
        </div>
        @endif
        <pre wire:poll.3s="showJob('{{ $selectedJob }}')" style="background:rgba(0,0,0,0.3);border:1px solid var(--glass-border);border-radius:10px;padding:14px;font-size:11px;line-height:1.5;max-height:260px;overflow:auto;color:#9cdcfe;white-space:pre-wrap;word-break:break-word;">{{ $jobLog }}</pre>
        @elseif(!count($jobs))
        <div style="text-align:center;padding:24px 20px;color:var(--text-secondary);font-size:12px;">
            No hay trabajos de copia en background.
        </div>
        @endif

        @if(count($jobs))
        <div class="table-responsive" style="margin-top:10px;">
            <table class="lp-table">
                <thead>
                    <tr>
                        <th>Trabajo</th>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Estado</th>
                        <th style="min-width:170px;">Progreso</th>
                        <th style="text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($jobs as $job)
                    <tr>
                        <td>
                            <code style="font-size:11px;">{{ $job['id'] }}</code>
                            <div style="font-size:10px;color:var(--text-muted);">{{ $job['started_at'] }}</div>
                        </td>
                        <td style="font-size:12px;word-break:break-all;">{{ $job['host'] }}:{{ $job['remote'] }}</td>
                        <td style="font-size:11px;color:var(--text-secondary);word-break:break-all;">{{ $job['target'] }}</td>
                        <td>
                            @php
                                $jobStatus = $job['status'];
                                $badgeClass = match ($jobStatus) {
                                    'done' => 'badge-success',
                                    'failed' => 'badge-danger',
                                    'stopped' => 'badge-warning',
                                    default => 'badge-info',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}" style="font-size:10px;">
                                @if($jobStatus === 'running')<i class="fa-solid fa-spinner fa-spin"></i> @endif{{ ucfirst($jobStatus) }}
                            </span>
                        </td>
                        <td>
                            @if($job['percent'] === null)
                            <span style="font-size:11px;color:var(--text-muted);">calculando…</span>
                            @else
                            @php
                                $fillClass = $jobStatus === 'done' ? 'success' : ($jobStatus === 'failed' || $jobStatus === 'stopped' ? 'danger' : 'accent');
                            @endphp
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="progress-bar" style="flex:1;min-width:90px;">
                                    <div class="progress-fill {{ $fillClass }}" style="width:{{ $job['percent'] }}%;"></div>
                                </div>
                                <span style="font-size:11px;color:var(--text-primary);white-space:nowrap;">{{ $job['percent'] }}%</span>
                            </div>
                            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">
                                {{ $fmtBytes($job['transferred_bytes']) }} / {{ $fmtBytes($job['total_bytes']) }}
                            </div>
                            @endif
                        </td>
                        <td style="text-align:right;">
                            <div style="display:inline-flex;gap:6px;">
                                <button wire:click="showJob('{{ $job['id'] }}')" class="btn btn-ghost btn-sm" title="Ver log">
                                    <i class="fa-solid fa-terminal" style="color:var(--accent-light);"></i>
                                </button>
                                <button wire:click="deleteJob('{{ $job['id'] }}')" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este trabajo?')" title="Eliminar trabajo">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Copy destination modal --}}
    @if($copyItem)
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated" style="max-width:520px;">
            <div class="lp-modal-header">
                <h3 class="panel-title" style="margin:0;">
                    <i class="fa-solid {{ $copyItem['type'] === 'dir' ? 'fa-arrow-down-to-bracket' : 'fa-arrow-down-to-line' }}"
                       style="color:var(--accent-light);margin-right:8px;"></i>
                    {{ $copyItem['type'] === 'dir' ? 'Copiar carpeta' : 'Copiar archivo' }}
                    <span style="color:var(--accent-light);font-family:monospace;font-size:12px;">{{ $copyItem['name'] }}</span>
                </h3>
                <button wire:click="closeCopyModal" class="lp-modal-close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="lp-modal-body" style="display:flex;flex-direction:column;gap:12px;">
                <div style="font-size:12px;color:var(--text-secondary);">
                    <i class="fa-solid fa-location-arrow" style="margin-right:6px;"></i>
                    Origen remoto:
                    <code style="color:var(--text-primary);">{{ $copyItem['remote'] }}</code>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Destino en este servidor <span style="color:var(--text-muted);font-weight:400;">— ruta absoluta</span></label>
                    <input type="text" wire:model="copyTarget" class="form-input" style="font-family:monospace;font-size:12px;" placeholder="/var/www/dominio">
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                        @if($copyItem['type'] === 'dir')
                        Se copiará toda la carpeta <b>{{ $copyItem['name'] }}</b> en esa ruta, en background y de forma continua.
                        @else
                        El archivo se guardará con el nombre <b>{{ $copyItem['name'] }}</b> dentro de esa carpeta.
                        @endif
                    </div>
                </div>
            </div>

            <div class="lp-modal-footer">
                <button wire:click="closeCopyModal" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="confirmCopy" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-arrow-down-to-bracket"></i>
                    {{ $copyItem['type'] === 'dir' ? 'Iniciar copia en background' : 'Copiar archivo' }}
                </button>
            </div>
        </div>
    </div>
    @endif

    <div wire:loading class="lp-loading-toast">
        <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
    </div>
</div>