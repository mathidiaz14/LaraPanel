<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">Tareas Programadas (Cron Jobs)</h1>
            <p style="color:var(--text-secondary);font-size:13px;">
                Configure comandos automáticos que se ejecutarán periódicamente en el servidor.
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

        {{-- Creation Panel --}}
        <div class="glass" style="padding:24px;">
            <h2 style="font-size:15px;font-weight:700;margin-bottom:14px;color:var(--text-primary);">
                <i class="fa-solid fa-clock" style="color:var(--accent-light);margin-right:8px;"></i>
                Nueva Tarea
            </h2>

            <form wire:submit.prevent="createCron">
                {{-- Label --}}
                <div class="form-group">
                    <label class="form-label">Etiqueta Identificadora</label>
                    <input type="text" wire:model="label" class="form-input" placeholder="ej. Renovar Certificados o Ejecutar Laravel Schedule">
                    @error('label') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
                </div>

                {{-- Command --}}
                <div class="form-group">
                    <label class="form-label">Comando a Ejecutar</label>
                    <input type="text" wire:model="command" class="form-input" placeholder="ej. php /var/www/miweb.com/artisan schedule:run">
                    @error('command') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
                </div>

                {{-- Presets dropdown --}}
                <div class="form-group">
                    <label class="form-label">Intervalo Predeterminado</label>
                    <select wire:model.live="preset" class="form-input">
                        <option value="custom">Configuración Manual (Expresión Cron)</option>
                        <option value="every_minute">Cada minuto (* * * * *)</option>
                        <option value="every_5_minutes">Cada 5 minutos (*/5 * * * *)</option>
                        <option value="hourly">Cada hora (0 * * * *)</option>
                        <option value="daily">Cada día a las 12 AM (0 0 * * *)</option>
                        <option value="weekly">Cada semana el domingo (0 0 * * 0)</option>
                        <option value="monthly">Cada mes el día 1 (0 0 1 * *)</option>
                    </select>
                </div>

                {{-- Cron Expression input --}}
                <div class="form-group">
                    <label class="form-label">Expresión Cron (minutos hora día mes semana)</label>
                    <input type="text" wire:model="schedule" class="form-input" style="font-family:monospace;" placeholder="* * * * *" {{ $preset !== 'custom' ? 'disabled' : '' }}>
                    @error('schedule') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">
                    <i class="fa-solid fa-plus-circle"></i> Agregar Tarea
                </button>
            </form>
        </div>

        {{-- Cron List Panel --}}
        <div class="glass" style="padding:24px;">
            <h2 style="font-size:15px;font-weight:700;margin-bottom:14px;color:var(--text-primary);">
                <i class="fa-solid fa-list" style="color:var(--accent-light);margin-right:8px;"></i>
                Tareas Configuradas
            </h2>

            @if($jobs->isEmpty())
            <div style="text-align:center;padding:60px 20px;color:var(--text-secondary);">
                <i class="fa-regular fa-clock" style="font-size:40px;opacity:0.25;margin-bottom:14px;display:block;"></i>
                No tiene tareas programadas creadas en este momento.
            </div>
            @else
            <div style="overflow-x:auto;">
                <table class="table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Etiqueta / Comando</th>
                            <th>Intervalo</th>
                            <th>Última Ejecución</th>
                            <th>Ejecutado (Fallidos)</th>
                            <th>Estado</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($jobs as $job)
                        <tr>
                            <td>
                                <strong style="color:var(--text-primary);display:block;">{{ $job->label }}</strong>
                                <code style="font-size:11px;color:var(--text-muted);word-break:break-all;">{{ $job->command }}</code>
                            </td>
                            <td>
                                <code style="font-size:11px;font-family:monospace;">{{ $job->schedule }}</code>
                            </td>
                            <td>
                                @if($job->last_run_at)
                                <span style="font-size:11px;display:block;">{{ $job->last_run_at->format('d M H:i') }}</span>
                                @if($job->last_run_status === 'success')
                                <span class="badge badge-success" style="font-size:10px;padding:1px 4px;">Éxito</span>
                                @else
                                <span class="badge badge-danger" style="font-size:10px;padding:1px 4px;">Fallo</span>
                                @endif
                                @else
                                <span style="color:var(--text-muted);font-size:11px;">—</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size:12px;">{{ $job->run_count }} <span style="color:var(--text-secondary);">({{ $job->fail_count }})</span></span>
                            </td>
                            <td>
                                <span wire:click="toggleStatus({{ $job->id }})" class="badge {{ $job->is_active ? 'badge-success' : 'badge-danger' }}" style="cursor:pointer;font-size:11px;">
                                    {{ $job->is_active ? 'Activo' : 'Pausado' }}
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:6px;">
                                    <button wire:click="runJobNow({{ $job->id }})" class="btn btn-ghost btn-sm" title="Ejecutar Ahora">
                                        <i class="fa-solid fa-play" style="color:var(--success);"></i>
                                    </button>
                                    <button wire:click="viewHistory({{ $job->id }})" class="btn btn-ghost btn-sm" title="Ver Historial">
                                        <i class="fa-solid fa-clock-rotate-left" style="color:var(--accent-light);"></i>
                                    </button>
                                    @if($job->last_run_output)
                                    <button wire:click="viewOutput({{ $job->id }})" class="btn btn-ghost btn-sm" title="Ver Última Salida">
                                        <i class="fa-solid fa-terminal" style="color:var(--warning);"></i>
                                    </button>
                                    @endif
                                    <button wire:click="deleteCron({{ $job->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que desea eliminar esta tarea cron?')" title="Eliminar Tarea">
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

    {{-- View Output Modal --}}
    @if($viewOutputId)
    @php
        $jobToView = $jobs->firstWhere('id', $viewOutputId);
    @endphp
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="max-width:600px;width:100%;padding:28px;margin:16px;display:flex;flex-direction:column;max-height:80vh;">
            <div style="display:flex;justify-content:between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;flex-shrink:0;">
                <h3 style="font-size:16px;font-weight:700;margin:0;">
                    <i class="fa-solid fa-terminal" style="color:var(--accent-light);margin-right:8px;"></i>
                    Registro de Salida
                </h3>
                <button wire:click="$set('viewOutputId', null)" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;margin-left:auto;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <p style="color:var(--text-secondary);font-size:12px;margin-bottom:12px;flex-shrink:0;">
                Salida generada en la última ejecución de: <strong style="color:var(--text-primary);">{{ $jobToView?->label }}</strong>
            </p>

            <div style="flex:1;overflow-y:auto;background:black;color:#00ff00;padding:16px;border-radius:8px;font-family:monospace;font-size:12px;white-space:pre-wrap;border:1px solid var(--glass-border);">{{ $jobToView?->last_run_output }}</div>

            <div style="display:flex;justify-content:flex-end;margin-top:16px;flex-shrink:0;">
                <button wire:click="$set('viewOutputId', null)" class="btn btn-ghost btn-sm">Cerrar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- History Modal --}}
    @if($viewHistoryId)
    @php
        $jobWithHistory = $jobs->firstWhere('id', $viewHistoryId);
    @endphp
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="max-width:680px;width:100%;padding:28px;margin:16px;display:flex;flex-direction:column;max-height:85vh;">
            <div style="display:flex;justify-content:between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;flex-shrink:0;">
                <h3 style="font-size:16px;font-weight:700;margin:0;">
                    <i class="fa-solid fa-clock-rotate-left" style="color:var(--accent-light);margin-right:8px;"></i>
                    Historial: {{ $jobWithHistory?->label }}
                </h3>
                <button wire:click="$set('viewHistoryId', null)" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;margin-left:auto;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div style="flex:1;overflow-y:auto;">
                @if($runLogs->isEmpty())
                <div style="text-align:center;padding:40px;color:var(--text-muted);">
                    <i class="fa-solid fa-list" style="font-size:32px;opacity:0.2;margin-bottom:10px;display:block;"></i>
                    No hay ejecuciones registradas aún. Use el botón <strong>Play</strong> para ejecutar la tarea.
                </div>
                @else
                <table class="table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Duración</th>
                            <th>Cód. Salida</th>
                            <th>Salida</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($runLogs as $log)
                        <tr>
                            <td><span style="font-size:12px;">{{ $log->ran_at->format('d/m/Y H:i:s') }}</span></td>
                            <td>
                                <span class="badge {{ $log->status === 'success' ? 'badge-success' : 'badge-danger' }}" style="font-size:11px;">
                                    {{ $log->status === 'success' ? 'Éxito' : 'Fallo' }}
                                </span>
                            </td>
                            <td><code style="font-size:11px;">{{ $log->durationFormatted() }}</code></td>
                            <td><code style="font-size:11px;color:{{ $log->exit_code === 0 ? 'var(--success)' : 'var(--danger)' }};">{{ $log->exit_code }}</code></td>
                            <td>
                                @if($log->output)
                                <details style="cursor:pointer;">
                                    <summary style="font-size:11px;color:var(--accent-light);">Ver output</summary>
                                    <pre style="font-size:11px;background:rgba(0,0,0,0.4);padding:8px;border-radius:6px;margin-top:6px;max-height:120px;overflow-y:auto;white-space:pre-wrap;color:#00ff00;">{{ $log->output }}</pre>
                                </details>
                                @else
                                <span style="color:var(--text-muted);font-size:11px;">(sin salida)</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>

            <div style="display:flex;justify-content:flex-end;margin-top:16px;flex-shrink:0;">
                <button wire:click="$set('viewHistoryId', null)" class="btn btn-ghost btn-sm">Cerrar</button>
            </div>
        </div>
    </div>
    @endif

    <div wire:loading style="position:fixed;bottom:24px;right:24px;z-index:300;">
        <div class="glass" style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
        </div>
    </div>
</div>
