<div>
    {{-- ── Tabs Navigation ──────────────────────────────────────────────────── --}}
    <div style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;">
        <button wire:click="setTab('scanner')" class="btn {{ $activeTab === 'scanner' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-magnifying-glass"></i> Escáner
        </button>
        <button wire:click="setTab('quarantine')" class="btn {{ $activeTab === 'quarantine' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-box-archive"></i> Cuarentena
            @if(count($quarantineFiles) > 0)
                <span class="badge badge-danger" style="font-size:9px;padding:2px 5px;margin-left:4px;">{{ count($quarantineFiles) }}</span>
            @endif
        </button>
        <button wire:click="setTab('definitions')" class="btn {{ $activeTab === 'definitions' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-database"></i> Definiciones
        </button>
    </div>

    {{-- ── ClamAV Not Installed Warning ────────────────────────────────────── --}}
    @if(!$isInstalled)
        <div class="glass" style="padding:48px;text-align:center;">
            <i class="fa-solid fa-shield-virus" style="font-size:52px;color:#f38ba8;margin-bottom:20px;opacity:0.6;display:block;"></i>
            <h3 style="font-size:20px;font-weight:700;margin-bottom:10px;color:var(--text-primary);">ClamAV no está instalado</h3>
            <p style="color:var(--text-secondary);font-size:14px;max-width:520px;margin:0 auto 24px;">
                El motor de antivirus ClamAV no se detectó en este sistema. Instálalo para usar el módulo de escaneo.
            </p>
            <div class="glass" style="padding:16px 24px;display:inline-block;text-align:left;border-radius:10px;background:rgba(0,0,0,0.3);">
                <code style="font-family:monospace;font-size:13px;color:#a6e3a1;">
                    sudo apt-get install -y clamav clamav-daemon<br>
                    sudo freshclam
                </code>
            </div>
        </div>
    @else

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: SCANNER                                                          --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'scanner')
        <div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">

            {{-- Left column: Scan form + output --}}
            <div>
                {{-- Scan Form --}}
                <div class="glass" style="padding:24px;margin-bottom:20px;">
                    <h3 style="font-size:15px;font-weight:700;margin-bottom:20px;color:var(--text-primary);">
                        <i class="fa-solid fa-virus-slash" style="color:#a6e3a1;margin-right:8px;"></i>
                        Escanear Directorio
                    </h3>

                    <form wire:submit.prevent="runScan">
                        {{-- Path input --}}
                        <div style="margin-bottom:16px;">
                            <label class="form-label">Ruta a escanear</label>
                            <div style="display:flex;gap:8px;">
                                <input type="text"
                                    wire:model="scanPath"
                                    class="form-input"
                                    placeholder="ej. /var/www o /home/usuario"
                                    style="font-family:monospace;">
                            </div>
                            @error('scanPath')
                                <span style="color:var(--danger);font-size:11px;margin-top:4px;display:block;">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Quick path presets --}}
                        <div style="margin-bottom:16px;">
                            <span style="font-size:11px;color:var(--text-muted);margin-right:8px;">Accesos rápidos:</span>
                            @foreach($pathPresets as $preset => $label)
                                <button type="button" wire:click="$set('scanPath', '{{ $preset }}')"
                                    class="btn btn-ghost btn-sm" style="font-size:10px;padding:2px 8px;margin:2px;font-family:monospace;">
                                    {{ $preset }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Quarantine toggle --}}
                        <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:rgba(243,139,168,0.05);border:1px solid rgba(243,139,168,0.15);border-radius:8px;margin-bottom:20px;">
                            <input type="checkbox" wire:model="withQuarantine" id="quarantine-toggle"
                                style="width:16px;height:16px;accent-color:#f38ba8;cursor:pointer;">
                            <label for="quarantine-toggle" style="cursor:pointer;font-size:13px;color:var(--text-primary);">
                                <strong>Mover infectados a cuarentena automáticamente</strong>
                                <span style="display:block;font-size:11px;color:var(--text-muted);margin-top:2px;">
                                    Los archivos detectados se moverán a <code>/var/larapanel/quarantine/</code>
                                </span>
                            </label>
                        </div>

                        {{-- Submit --}}
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"
                            style="width:100%;justify-content:center;padding:12px;">
                            <span wire:loading.remove wire:target="runScan">
                                <i class="fa-solid fa-shield-halved"></i> Iniciar Escaneo
                            </span>
                            <span wire:loading wire:target="runScan">
                                <i class="fa-solid fa-spinner fa-spin"></i> Escaneando... (puede tardar varios minutos)
                            </span>
                        </button>
                    </form>
                </div>

                {{-- Scan Result Summary --}}
                @if($lastScanResult)
                    @php
                        $isClean    = $lastScanResult['status'] === 'clean';
                        $isInfected = $lastScanResult['status'] === 'infected';
                        $isError    = $lastScanResult['status'] === 'error';
                        $accentColor = $isClean ? '#a6e3a1' : ($isInfected ? '#f38ba8' : '#f9e2af');
                        $bgColor     = $isClean ? 'rgba(166,227,161,0.05)' : ($isInfected ? 'rgba(243,139,168,0.05)' : 'rgba(249,226,175,0.05)');
                    @endphp
                    <div class="glass" style="padding:20px;margin-bottom:20px;border-color:{{ $accentColor }}22;background:{{ $bgColor }};">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                            <i class="fa-solid {{ $isClean ? 'fa-shield-check' : ($isInfected ? 'fa-biohazard' : 'fa-triangle-exclamation') }}"
                               style="font-size:24px;color:{{ $accentColor }};"></i>
                            <div>
                                <div style="font-size:15px;font-weight:700;color:var(--text-primary);">
                                    {{ $isClean ? '✅ Sistema Limpio' : ($isInfected ? '🦠 Amenazas Detectadas' : '⚠️ Error en el Escaneo') }}
                                </div>
                                <div style="font-size:11px;color:var(--text-muted);">{{ $scanPath }}</div>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
                            <div style="text-align:center;padding:12px;background:rgba(255,255,255,0.03);border-radius:8px;border:1px solid var(--glass-border);">
                                <div style="font-size:22px;font-weight:700;color:var(--text-primary);">{{ number_format($lastScanResult['files_scanned']) }}</div>
                                <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Archivos escaneados</div>
                            </div>
                            <div style="text-align:center;padding:12px;background:rgba(255,255,255,0.03);border-radius:8px;border:1px solid {{ $lastScanResult['infected_count'] > 0 ? 'rgba(243,139,168,0.3)' : 'var(--glass-border)' }};">
                                <div style="font-size:22px;font-weight:700;color:{{ $lastScanResult['infected_count'] > 0 ? '#f38ba8' : 'var(--text-primary)' }};">{{ $lastScanResult['infected_count'] }}</div>
                                <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Infectados</div>
                            </div>
                            <div style="text-align:center;padding:12px;background:rgba(255,255,255,0.03);border-radius:8px;border:1px solid var(--glass-border);">
                                <div style="font-size:22px;font-weight:700;color:var(--text-primary);">{{ $lastScanResult['error_count'] }}</div>
                                <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Errores</div>
                            </div>
                            <div style="text-align:center;padding:12px;background:rgba(255,255,255,0.03);border-radius:8px;border:1px solid var(--glass-border);">
                                <div style="font-size:22px;font-weight:700;color:var(--text-primary);">{{ $lastScanResult['duration_seconds'] }}s</div>
                                <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Duración</div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Scan output terminal --}}
                @if($scanOutput)
                    <div class="glass" style="padding:20px;">
                        <h4 style="font-size:13px;font-weight:700;margin-bottom:12px;color:var(--text-primary);">
                            <i class="fa-solid fa-terminal"></i> Salida del Escaneo
                        </h4>
                        <pre style="background:rgba(0,0,0,0.85);border:1px solid var(--glass-border);border-radius:8px;padding:16px;font-family:monospace;font-size:11px;color:#a6e3a1;line-height:1.6;white-space:pre-wrap;max-height:350px;overflow-y:auto;text-align:left;">{{ $scanOutput }}</pre>
                    </div>
                @endif
            </div>

            {{-- Right column: History --}}
            <div>
                <div class="glass" style="padding:16px;">
                    <h3 style="font-size:13px;font-weight:700;margin-bottom:14px;color:var(--text-primary);">
                        <i class="fa-solid fa-clock-rotate-left"></i> Historial de Escaneos
                    </h3>

                    @if(empty($scanHistory))
                        <div style="text-align:center;padding:24px 12px;color:var(--text-muted);font-size:12px;">
                            <i class="fa-solid fa-shield" style="font-size:28px;opacity:0.2;margin-bottom:8px;display:block;"></i>
                            Sin escaneos aún.
                        </div>
                    @else
                        <div style="display:flex;flex-direction:column;gap:8px;max-height:580px;overflow-y:auto;">
                            @foreach($scanHistory as $hist)
                                <button wire:click="viewScanDetail({{ $hist['id'] }})"
                                    style="width:100%;text-align:left;padding:10px 12px;border-radius:8px;border:1px solid var(--glass-border);background:rgba(255,255,255,0.02);cursor:pointer;transition:background 0.2s;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                                        <span style="font-family:monospace;font-size:11px;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;">
                                            {{ $hist['path'] }}
                                        </span>
                                        <span class="badge {{ $hist['badge_class'] }}" style="font-size:9px;padding:2px 6px;flex-shrink:0;">
                                            {{ $hist['status'] }}
                                        </span>
                                    </div>
                                    <div style="font-size:10px;color:var(--text-muted);">
                                        {{ $hist['files_scanned'] }} archivos · {{ $hist['infected_count'] }} infectados · {{ $hist['duration'] }}
                                    </div>
                                    <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">{{ $hist['created_at'] }}</div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: QUARANTINE                                                       --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'quarantine')
        <div class="glass" style="padding:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <div>
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);">
                        <i class="fa-solid fa-box-archive" style="color:#f38ba8;margin-right:8px;"></i>
                        Archivos en Cuarentena
                    </h3>
                    <p style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                        Los archivos aquí son inaccesibles para el sistema. Puedes eliminarlos o restaurarlos.
                    </p>
                </div>
                <span style="font-size:13px;color:var(--text-muted);">
                    {{ count($quarantineFiles) }} archivo{{ count($quarantineFiles) !== 1 ? 's' : '' }}
                </span>
            </div>

            @if(empty($quarantineFiles))
                <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
                    <i class="fa-solid fa-shield-check" style="font-size:48px;opacity:0.2;margin-bottom:16px;display:block;color:#a6e3a1;"></i>
                    <div style="font-size:15px;font-weight:600;margin-bottom:6px;">Cuarentena vacía</div>
                    <div style="font-size:13px;">No hay archivos aislados. El sistema está limpio.</div>
                </div>
            @else
                <div style="overflow-x:auto;">
                    <table class="table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Amenaza detectada</th>
                                <th>Ruta original</th>
                                <th>Tamaño</th>
                                <th>Fecha</th>
                                <th>Estado en disco</th>
                                <th style="text-align:right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quarantineFiles as $qf)
                                <tr>
                                    <td>
                                        <span class="badge badge-danger" style="font-size:10px;">
                                            <i class="fa-solid fa-virus" style="margin-right:4px;"></i>
                                            {{ $qf['threat_name'] }}
                                        </span>
                                    </td>
                                    <td style="font-family:monospace;font-size:11px;color:var(--text-secondary);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $qf['original_path'] }}">
                                        {{ $qf['original_path'] }}
                                    </td>
                                    <td style="font-size:12px;white-space:nowrap;">{{ $qf['file_size'] }}</td>
                                    <td style="font-size:11px;color:var(--text-muted);white-space:nowrap;">{{ $qf['created_at'] }}</td>
                                    <td>
                                        @if($qf['exists'])
                                            <span class="badge badge-warning" style="font-size:9px;">En cuarentena</span>
                                        @else
                                            <span class="badge badge-secondary" style="font-size:9px;">No en disco</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <button wire:click="restoreQuarantineFile({{ $qf['id'] }})"
                                            class="btn btn-ghost btn-sm"
                                            style="color:#89b4fa;"
                                            onclick="return confirm('¿Restaurar este archivo a su ubicación original? Solo haz esto si estás seguro de que es un falso positivo.')"
                                            title="Restaurar">
                                            <i class="fa-solid fa-rotate-left"></i>
                                        </button>
                                        <button wire:click="deleteQuarantineFile({{ $qf['id'] }})"
                                            class="btn btn-ghost btn-sm"
                                            style="color:var(--danger);"
                                            onclick="return confirm('¿Eliminar PERMANENTEMENTE este archivo infectado? Esta acción no se puede deshacer.')"
                                            title="Eliminar permanentemente">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: DEFINITIONS                                                      --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'definitions')
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

            {{-- Status card --}}
            <div class="glass" style="padding:24px;">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:20px;color:var(--text-primary);">
                    <i class="fa-solid fa-circle-info" style="color:#89b4fa;margin-right:8px;"></i>
                    Estado del Motor
                </h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    {{-- Version --}}
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:12px 16px;background:rgba(255,255,255,0.02);border-radius:8px;border:1px solid var(--glass-border);">
                        <div>
                            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">Versión instalada</div>
                            <div style="font-size:12px;font-family:monospace;color:var(--text-primary);">{{ $clamVersion }}</div>
                        </div>
                        <i class="fa-solid fa-shield-virus" style="color:#89b4fa;font-size:20px;opacity:0.6;"></i>
                    </div>

                    {{-- DB Version --}}
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:12px 16px;background:rgba(255,255,255,0.02);border-radius:8px;border:1px solid var(--glass-border);">
                        <div>
                            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">Base de datos de firmas</div>
                            <div style="font-size:13px;font-weight:600;color:var(--text-primary);">v{{ $defsInfo['db_version'] ?? 'N/A' }}</div>
                            <div style="font-size:11px;color:var(--text-muted);">{{ $defsInfo['db_date'] ?? 'Fecha desconocida' }}</div>
                        </div>
                        <i class="fa-solid fa-database" style="color:#cba6f7;font-size:20px;opacity:0.6;"></i>
                    </div>

                    {{-- Daemon status --}}
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:rgba(255,255,255,0.02);border-radius:8px;border:1px solid var(--glass-border);">
                        <div>
                            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">clamav-daemon</div>
                            <span class="badge {{ $daemonStatus === 'active' ? 'badge-success' : 'badge-secondary' }}">
                                {{ $daemonStatus }}
                            </span>
                        </div>
                        <i class="fa-solid fa-microchip" style="font-size:20px;opacity:0.4;"></i>
                    </div>
                </div>
            </div>

            {{-- Update card --}}
            <div class="glass" style="padding:24px;">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:8px;color:var(--text-primary);">
                    <i class="fa-solid fa-cloud-arrow-down" style="color:#a6e3a1;margin-right:8px;"></i>
                    Actualizar Definiciones
                </h3>
                <p style="font-size:12px;color:var(--text-muted);margin-bottom:20px;">
                    Descarga la última base de datos de firmas de virus desde los servidores de ClamAV. Se recomienda mantenerla actualizada diariamente.
                </p>

                <button wire:click="updateDefinitions" class="btn btn-primary" wire:loading.attr="disabled"
                    style="width:100%;justify-content:center;padding:12px;margin-bottom:16px;">
                    <span wire:loading.remove wire:target="updateDefinitions">
                        <i class="fa-solid fa-arrows-rotate"></i> Actualizar ahora (freshclam)
                    </span>
                    <span wire:loading wire:target="updateDefinitions">
                        <i class="fa-solid fa-spinner fa-spin"></i> Descargando definiciones...
                    </span>
                </button>

                @if($updateOutput)
                    <pre style="background:rgba(0,0,0,0.85);border:1px solid var(--glass-border);border-radius:8px;padding:14px;font-family:monospace;font-size:11px;color:#cdd6f4;line-height:1.6;white-space:pre-wrap;max-height:280px;overflow-y:auto;text-align:left;">{{ $updateOutput }}</pre>
                @endif

                <div style="margin-top:16px;padding:12px;background:rgba(137,180,250,0.05);border:1px solid rgba(137,180,250,0.15);border-radius:8px;font-size:11px;color:var(--text-muted);">
                    <i class="fa-solid fa-circle-info" style="color:#89b4fa;margin-right:6px;"></i>
                    Para automatizar las actualizaciones, agrega al cron:<br>
                    <code style="font-family:monospace;color:#89b4fa;display:block;margin-top:6px;">0 3 * * * /usr/bin/freshclam --quiet</code>
                </div>
            </div>

        </div>
    @endif

    @endif {{-- /isInstalled --}}
</div>
