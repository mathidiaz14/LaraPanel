<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Backups del Servidor</h1>
            <p class="page-subtitle">
                Cree y gestione copias de seguridad de archivos y bases de datos. Los backups se almacenan localmente en el servidor.
            </p>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="stats-row">
        @php
            $completedCount = $backups->where('status', 'completed')->count();
            $totalSize = $backups->where('status', 'completed')->sum('size_bytes');
            $failedCount = $backups->where('status', 'failed')->count();
            $lastBackup = $backups->where('status', 'completed')->first();
            // Format total size
            $ts = $totalSize;
            $tu = ['B','KB','MB','GB'];
            for($ti=0; $ts>=1024 && $ti<3; $ti++) $ts/=1024;
            $totalSizeFmt = $ts>0 ? round($ts,1).' '.$tu[$ti] : '0 B';
        @endphp
        <div class="glass" style="padding:16px;text-align:center;">
            <div style="font-size:24px;font-weight:700;color:var(--accent-light);">{{ $completedCount }}</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">Backups Completados</div>
        </div>
        <div class="glass" style="padding:16px;text-align:center;">
            <div style="font-size:24px;font-weight:700;color:var(--success);">{{ $totalSizeFmt }}</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">Espacio Utilizado</div>
        </div>
        <div class="glass" style="padding:16px;text-align:center;">
            <div style="font-size:24px;font-weight:700;color:{{ $failedCount > 0 ? 'var(--danger)' : 'var(--text-muted)' }};">{{ $failedCount }}</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">Backups Fallidos</div>
        </div>
        <div class="glass" style="padding:16px;text-align:center;">
            <div style="font-size:13px;font-weight:600;color:var(--text-primary);">{{ $lastBackup ? $lastBackup->created_at->diffForHumans() : 'Nunca' }}</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">Último Backup</div>
        </div>
    </div>

    {{-- Alerts --}}
    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    <div class="lp-two-col">

        {{-- Create Backup Panel --}}
        <div class="glass lp-panel">
            <h2 class="panel-title">
                <i class="fa-solid fa-plus-circle" style="color:var(--accent-light);"></i>
                Nuevo Backup
            </h2>

            <form wire:submit.prevent="runBackup">
                <div class="form-group">
                    <label class="form-label">Etiqueta del Backup</label>
                    <input type="text" wire:model="label" class="form-input" placeholder="ej. Backup previo actualización">
                    @error('label') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                {{-- Type Selector --}}
                <div class="form-group">
                    <label class="form-label">Tipo de Backup</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;">
                        @foreach(['full' => ['fa-server', 'Completo', 'Archivos + DB'], 'files' => ['fa-folder-open', 'Archivos', 'Solo ficheros'], 'database' => ['fa-database', 'Base de Datos', 'Solo MySQL']] as $val => [$icon, $lbl, $desc])
                        <label style="cursor:pointer;">
                            <input type="radio" wire:model.live="type" value="{{ $val }}" style="display:none;">
                            <div style="padding:10px 6px;border-radius:8px;text-align:center;border:1px solid {{ $type === $val ? 'rgba(99,102,241,0.5)' : 'var(--glass-border)' }};background:{{ $type === $val ? 'rgba(99,102,241,0.12)' : 'var(--glass-bg)' }};transition:all 0.2s;cursor:pointer;">
                                <i class="fa-solid {{ $icon }}" style="font-size:16px;color:{{ $type === $val ? 'var(--accent-light)' : 'var(--text-muted)' }};margin-bottom:4px;display:block;"></i>
                                <div style="font-size:11px;font-weight:600;color:{{ $type === $val ? 'var(--text-primary)' : 'var(--text-secondary)' }};">{{ $lbl }}</div>
                                <div style="font-size:10px;color:var(--text-muted);">{{ $desc }}</div>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Domain Filter --}}
                <div class="form-group">
                    <label class="form-label">Dominio <span style="color:var(--text-muted);font-weight:400;">— opcional, vacío = todos</span></label>
                    <select wire:model="domainId" class="form-input">
                        <option value="">Todos los dominios</option>
                        @foreach($domains as $dom)
                        <option value="{{ $dom->id }}">{{ $dom->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Notes --}}
                <div class="form-group">
                    <label class="form-label">Notas <span style="color:var(--text-muted);font-weight:400;">— opcional</span></label>
                    <input type="text" wire:model="notes" class="form-input" placeholder="ej. Antes de actualizar a v2.5">
                </div>

                <div style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;color:var(--text-secondary);">
                    <i class="fa-solid fa-info-circle" style="color:var(--accent-light);margin-right:6px;"></i>
                    El backup se ejecuta de forma <strong style="color:var(--text-primary);">síncrona</strong>. Para backups grandes, puede tardar varios minutos.
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;" wire:loading.attr="disabled">
                    <span wire:loading.remove>
                        <i class="fa-solid fa-cloud-arrow-up"></i> Iniciar Backup
                    </span>
                    <span wire:loading>
                        <i class="fa-solid fa-spinner fa-spin"></i> Creando backup...
                    </span>
                </button>
            </form>
        </div>

        {{-- Create Schedule Panel --}}
        <div class="glass lp-panel" style="margin-top:20px;">
            <h2 class="panel-title">
                <i class="fa-solid fa-clock" style="color:var(--accent-light);"></i>
                Programar Backups (Cron)
            </h2>

            <form wire:submit.prevent="createSchedule">
                <div class="form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label class="form-label">Tipo</label>
                        <select wire:model="schedType" class="form-input">
                            <option value="full">Completo (Archivos + DB)</option>
                            <option value="files">Solo Archivos</option>
                            <option value="database">Solo Base de Datos</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Destino (Disco)</label>
                        <select wire:model="schedDisk" class="form-input">
                            <option value="local">Disco Local</option>
                            <option value="s3">Nube (S3 / Backblaze)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label class="form-label">Frecuencia</label>
                        <select wire:model="schedFrequency" class="form-input">
                            <option value="daily">Diario</option>
                            <option value="weekly">Semanal</option>
                            <option value="monthly">Mensual</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Retención</label>
                        <input type="number" wire:model="schedRetention" min="1" max="30" class="form-input">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Dominio <span style="color:var(--text-muted);font-weight:400;">— opcional, vacío = todos</span></label>
                    <select wire:model="schedDomainId" class="form-input">
                        <option value="">Todos los dominios</option>
                        @foreach($domains as $dom)
                        <option value="{{ $dom->id }}">{{ $dom->name }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary" style="width:100%;justify-content:center;" wire:loading.attr="disabled">
                    <i class="fa-solid fa-calendar-plus"></i> Añadir Programación
                </button>
            </form>
        </div>
    </div>

        <div>
            {{-- Schedules List --}}
            @if($schedules->isNotEmpty())
            <div class="glass lp-panel" style="margin-bottom:20px;">
                <h2 class="panel-title">
                    <i class="fa-solid fa-calendar-check" style="color:var(--accent-light);"></i>
                    Backups Programados Activos
                </h2>
                <div style="overflow-x:auto;">
                    <table class="lp-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Destino</th>
                                <th>Frecuencia</th>
                                <th>Próxima Ejecución</th>
                                <th style="text-align:right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($schedules as $sched)
                            <tr>
                                <td>
                                    <span style="font-weight:600;font-size:13px;">{{ ucfirst($sched->type) }}</span>
                                    <div style="font-size:11px;color:var(--text-muted);">{{ $sched->domain?->name ?? 'Todos' }}</div>
                                </td>
                                <td><span class="badge badge-muted" style="font-size:11px;">{{ strtoupper($sched->disk) }}</span></td>
                                <td>{{ ucfirst($sched->frequency) }} (Ret: {{ $sched->retention_count }})</td>
                                <td style="font-size:12px;color:var(--text-secondary);">{{ $sched->next_run_at ? $sched->next_run_at->diffForHumans() : 'Pendiente' }}</td>
                                <td style="text-align:right;">
                                    <button wire:click="deleteSchedule({{ $sched->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que desea eliminar esta programación?')" title="Eliminar">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Backups List --}}
            <div class="glass lp-panel">
            <h2 class="panel-title">
                <i class="fa-solid fa-list" style="color:var(--accent-light);"></i>
                Historial de Backups
            </h2>

            @if($backups->isEmpty())
            <div style="text-align:center;padding:60px 20px;color:var(--text-secondary);">
                <i class="fa-solid fa-box-archive" style="font-size:40px;opacity:0.25;margin-bottom:14px;display:block;"></i>
                No tiene backups creados. Cree su primer backup de seguridad.
            </div>
            @else
            <div style="overflow-x:auto;">
                <table class="lp-table">
                    <thead>
                        <tr>
                            <th>Etiqueta</th>
                            <th>Tipo</th>
                            <th>Tamaño</th>
                            <th>Duración</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($backups as $backup)
                        <tr>
                            <td>
                                <strong style="color:var(--text-primary);display:block;font-size:13px;">{{ $backup->label }}</strong>
                                @if($backup->domain)
                                <span style="font-size:11px;color:var(--text-muted);">{{ $backup->domain->name }}</span>
                                @else
                                <span style="font-size:11px;color:var(--text-muted);">Todos los dominios</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $typeConfig = ['full' => ['fa-server','var(--accent-light)','Completo'], 'files' => ['fa-folder','var(--warning)','Archivos'], 'database' => ['fa-database','var(--success)','Base de Datos']];
                                    [$tIcon, $tColor, $tLabel] = $typeConfig[$backup->type] ?? ['fa-box','var(--text-muted)','—'];
                                @endphp
                                <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;">
                                    <i class="fa-solid {{ $tIcon }}" style="color:{{ $tColor }};"></i>
                                    {{ $tLabel }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size:12px;font-weight:600;">{{ $backup->sizeFormatted() }}</span>
                            </td>
                            <td>
                                <span style="font-size:12px;color:var(--text-secondary);">{{ $backup->durationFormatted() }}</span>
                            </td>
                            <td>
                                @php
                                    $statusConfig = ['completed' => ['badge-success','Completo'], 'failed' => ['badge-danger','Fallido'], 'running' => ['badge-accent','Ejecutando...'], 'pending' => ['badge-muted','Pendiente']];
                                    [$sBadge, $sLabel] = $statusConfig[$backup->status] ?? ['badge-muted','—'];
                                @endphp
                                <span class="badge {{ $sBadge }}" style="font-size:11px;">{{ $sLabel }}</span>
                            </td>
                            <td>
                                <span style="font-size:12px;color:var(--text-secondary);">{{ $backup->created_at->format('d M H:i') }}</span>
                            </td>
                            <td style="text-align:right;">
                                <div class="lp-row-actions">
                                    <button wire:click="viewBackup({{ $backup->id }})" class="btn btn-ghost btn-sm" title="Ver detalles">
                                        <i class="fa-solid fa-eye" style="color:var(--accent-light);"></i>
                                    </button>
                                    @if($backup->status === 'completed' && ($backup->filename || $backup->remote_path))
                                    <button wire:click="downloadBackup({{ $backup->id }})" class="btn btn-ghost btn-sm" title="Descargar">
                                        <i class="fa-solid fa-download" style="color:var(--success);"></i>
                                    </button>
                                    <button wire:click="restoreBackup({{ $backup->id }})" class="btn btn-ghost btn-sm" onclick="return confirm('ATENCIÓN: Restaurar un backup reemplazará los archivos y/o base de datos actuales de forma irreversible. ¿Deseas continuar?')" title="Restaurar (Reemplaza datos actuales)">
                                        <i class="fa-solid fa-clock-rotate-left" style="color:var(--warning);"></i>
                                    </button>
                                    @endif
                                    <button wire:click="deleteBackup({{ $backup->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que desea eliminar este backup y su archivo?')" title="Eliminar">
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

    </div>

    {{-- View Detail Modal --}}
    @if($viewingId && $viewing)
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated" style="max-width:520px;">
            <div class="lp-modal-header">
                <h3 class="panel-title" style="margin:0;">
                    <i class="fa-solid fa-box-archive" style="color:var(--accent-light);"></i>
                    Detalles del Backup
                </h3>
                <button wire:click="$set('viewingId', null)" class="lp-modal-close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <div class="lp-modal-body">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                @foreach([
                    ['Etiqueta', $viewing->label],
                    ['Tipo', ucfirst($viewing->type)],
                    ['Estado', ucfirst($viewing->status)],
                    ['Tamaño', $viewing->sizeFormatted()],
                    ['Duración', $viewing->durationFormatted()],
                    ['Dominio', $viewing->domain?->name ?? 'Todos'],
                    ['Inicio', $viewing->started_at?->format('d/m/Y H:i:s') ?? '—'],
                    ['Fin', $viewing->completed_at?->format('d/m/Y H:i:s') ?? '—'],
                ] as [$lbl, $val])
                <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:10px 12px;">
                    <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">{{ $lbl }}</div>
                    <div style="font-size:13px;color:var(--text-primary);margin-top:2px;font-weight:500;">{{ $val }}</div>
                </div>
                @endforeach
            </div>

            @if($viewing->filename)
            <div style="background:rgba(0,0,0,0.3);border-radius:8px;padding:10px 14px;margin-bottom:16px;">
                <div style="font-size:10px;color:var(--text-muted);margin-bottom:4px;">ARCHIVO</div>
                <code style="font-size:11px;color:var(--success);word-break:break-all;">{{ $viewing->filename }}</code>
            </div>
            @endif

            @if($viewing->notes)
            <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:10px 14px;margin-bottom:16px;">
                <div style="font-size:10px;color:var(--text-muted);margin-bottom:4px;">NOTAS</div>
                <div style="font-size:12px;color:var(--text-secondary);">{{ $viewing->notes }}</div>
            </div>
            @endif

            @if($viewing->error_message)
            <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:10px 14px;margin-bottom:16px;">
                <div style="font-size:10px;color:var(--danger);margin-bottom:4px;">ERROR</div>
                <div style="font-size:12px;color:var(--danger);">{{ $viewing->error_message }}</div>
            </div>
            @endif

            </div>

            <div class="lp-modal-footer">
                <button wire:click="$set('viewingId', null)" class="btn btn-ghost">Cerrar</button>
            </div>
        </div>
    </div>
    @endif

    <div wire:loading.delay class="lp-loading-toast">
        <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
    </div>
</div>
