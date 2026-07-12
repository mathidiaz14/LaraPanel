<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Cuentas FTP</h1>
            <p class="page-subtitle">
                Cree usuarios FTP adicionales para permitir la carga y descarga de archivos en directorios específicos.
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
        <div class="glass lp-panel">
            <h2 class="panel-title">
                <i class="fa-solid fa-user-shield" style="color:var(--accent-light);margin-right:8px;"></i>
                Nuevo Usuario FTP
            </h2>

            <form wire:submit.prevent="createFtp">
                {{-- Username / Domain --}}
                <div class="form-group">
                    <label class="form-label">Usuario FTP</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <input type="text" wire:model="usernameSuffix" class="form-input" style="margin:0;flex:1;" placeholder="ej. backup">
                        <span style="color:var(--text-muted);font-weight:600;">@</span>
                        <select wire:model="domainId" class="form-input" style="margin:0;flex:1.5;">
                            <option value="">Seleccione dominio...</option>
                            @foreach($domains as $dom)
                            <option value="{{ $dom->id }}">{{ $dom->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('usernameSuffix') <div class="form-error">{{ $message }}</div> @enderror
                    @error('domainId') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                {{-- Password --}}
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" wire:model="password" class="form-input" placeholder="Contraseña segura" style="margin-bottom:0;">
                        <button type="button" wire:click="generateRandomPassword" class="btn btn-ghost" style="flex-shrink:0;">
                            Generar
                        </button>
                    </div>
                    @error('password') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                {{-- Subdirectory under domain --}}
                <div class="form-group">
                    <label class="form-label">Directorio de Inicio (relativo al dominio) <span style="font-weight:400;color:var(--text-muted);">— opcional</span></label>
                    <div style="display:flex;align-items:center;background:rgba(255,255,255,0.05);border:1px solid var(--glass-border);border-radius:8px;overflow:hidden;">
                        <span style="padding:10px 14px;background:rgba(255,255,255,0.05);color:var(--text-muted);font-size:13px;border-right:1px solid var(--glass-border);font-family:monospace;">
                            /
                        </span>
                        <input type="text" wire:model="subdir" class="form-input" style="border:none;background:none;margin:0;" placeholder="ej. public_html o dejar vacío para la raíz">
                    </div>
                    @error('subdir') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                {{-- Quota MB --}}
                <div class="form-group">
                    <label class="form-label">Límite de Espacio (MB) <span style="font-weight:400;color:var(--text-muted);">— 0 para ilimitado</span></label>
                    <input type="number" wire:model="quotaMb" class="form-input" placeholder="0">
                    @error('quotaMb') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                {{-- Read only checkbox --}}
                <div class="form-group" style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" id="readonly" wire:model="readonly" style="width:18px;height:18px;cursor:pointer;accent-color:var(--accent-light);">
                    <label for="readonly" class="form-label" style="margin:0;cursor:pointer;">¿Acceso de Solo Lectura?</label>
                </div>

                {{-- Advanced settings --}}
                <div style="border-top:1px solid var(--glass-border);padding-top:14px;margin-top:4px;">
                    <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">Configuración Avanzada</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" style="font-size:11px;">Conexiones Simultáneas</label>
                            <input type="number" wire:model="maxConnections" class="form-input" min="1" max="50" placeholder="5" style="font-size:12px;">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" style="font-size:11px;">Ancho de Banda (MB/s) <span style="color:var(--text-muted);">(0=ilim)</span></label>
                            <input type="number" wire:model="bandwidthLimitMb" class="form-input" min="0" placeholder="0" style="font-size:12px;">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label class="form-label" style="font-size:11px;">IPs Permitidas <span style="color:var(--text-muted);">(vacío=todas)</span></label>
                        <input type="text" wire:model="allowedIps" class="form-input" placeholder="192.168.1.1, 10.0.0.0/8" style="font-size:11px;font-family:monospace;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-size:11px;">Notas <span style="color:var(--text-muted);">— opcional</span></label>
                        <input type="text" wire:model="notes" class="form-input" placeholder="ej. Backup diario del sitio" style="font-size:12px;">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:14px;">
                    <i class="fa-solid fa-plus-circle"></i> Crear Usuario FTP
                </button>
            </form>
        </div>

        {{-- FTP Accounts List --}}
        <div class="glass lp-panel">
            <h2 class="panel-title">
                <i class="fa-solid fa-list" style="color:var(--accent-light);margin-right:8px;"></i>
                Usuarios FTP Activos
            </h2>

            @if($ftps->isEmpty())
            <div style="text-align:center;padding:60px 20px;color:var(--text-secondary);">
                <i class="fa-solid fa-user-slash" style="font-size:40px;opacity:0.25;margin-bottom:14px;display:block;"></i>
                No tiene usuarios FTP configurados en este momento.
            </div>
            @else
            <div class="table-responsive">
                <table class="lp-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Directorio Raíz</th>
                            <th>Cuota</th>
                            <th>Permiso</th>
                            <th>Ancho de Banda</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ftps as $ftp)
                        <tr>
                            <td>
                                <strong style="color:var(--text-primary);">{{ $ftp->username }}</strong>
                            </td>
                            <td>
                                <code style="font-size:11px;word-break:break-all;">{{ basename(dirname($ftp->home_directory)) }}/{{ basename($ftp->home_directory) }}</code>
                            </td>
                            <td>
                                <span style="font-size:12px;font-weight:500;">{{ $ftp->quotaFormatted() }}</span>
                            </td>
                            <td>
                                <span wire:click="toggleReadonly({{ $ftp->id }})" class="badge {{ $ftp->readonly ? 'badge-danger' : 'badge-success' }}" style="cursor:pointer;font-size:11px;">
                                    {{ $ftp->readonly ? 'Solo Lectura' : 'Lectura/Escritura' }}
                                </span>
                            </td>
                            <td>
                                @php $bw = $ftp->bandwidth_limit_bytes ?? 0; @endphp
                                <span style="font-size:12px;color:var(--text-secondary);">
                                    {{ $bw > 0 ? round($bw/1048576) . ' MB/s' : '∞' }}
                                </span>
                                @if(!empty($ftp->allowed_ips))
                                <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">{{ count($ftp->allowed_ips) }} IP(s)</div>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:6px;">
                                    <button wire:click="openIpEdit({{ $ftp->id }})" class="btn btn-ghost btn-sm" title="Restricciones de acceso">
                                        <i class="fa-solid fa-network-wired" style="color:var(--accent-light);"></i>
                                    </button>
                                    <button wire:click="openPathEdit({{ $ftp->id }})" class="btn btn-ghost btn-sm" title="Editar Directorio Raíz">
                                        <i class="fa-solid fa-folder-open" style="color:#10b981;"></i>
                                    </button>
                                    <button wire:click="confirmChangePassword({{ $ftp->id }})" class="btn btn-ghost btn-sm" title="Cambiar Contraseña">
                                        <i class="fa-solid fa-key" style="color:var(--warning);"></i>
                                    </button>
                                    <button wire:click="deleteFtp({{ $ftp->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que desea eliminar este usuario FTP?')" title="Eliminar Usuario">
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

    {{-- IP / Bandwidth Restriction Modal --}}
    @if($editIpId)
    @php $ftpForIp = $ftps->firstWhere('id', $editIpId); @endphp
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated" style="max-width:480px;">
            <div class="lp-modal-header">
                <h3 class="panel-title" style="margin:0;">
                    <i class="fa-solid fa-network-wired" style="color:var(--accent-light);margin-right:8px;"></i>
                    Restricciones: <span style="color:var(--accent-light);">{{ $ftpForIp?->username }}</span>
                </h3>
                <button wire:click="$set('editIpId', null)" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="lp-modal-body">
                <div class="form-group">
                <label class="form-label">IPs Permitidas <span style="color:var(--text-muted);font-weight:400;">— vacío = sin restricción</span></label>
                <textarea wire:model="editAllowedIps" class="form-input" style="font-family:monospace;font-size:12px;height:80px;" placeholder="192.168.1.10&#10;10.0.0.0/8&#10;203.0.113.5"></textarea>
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Una IP o rango CIDR por línea, o separados por coma.</div>
            </div>

            <div class="form-group">
                <label class="form-label">Límite de Ancho de Banda <span style="color:var(--text-muted);font-weight:400;">(MB/s, 0 = ilimitado)</span></label>
                <input type="number" wire:model="editBandwidthMb" class="form-input" min="0" placeholder="0">
            </div>

            <div class="lp-modal-footer">
                <button wire:click="$set('editIpId', null)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="saveIpEdit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Change Path Modal --}}
    @if($editPathId)
    @php $ftpForPath = $ftps->firstWhere('id', $editPathId); @endphp
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated" style="max-width:480px;">
            <div class="lp-modal-header">
                <h3 class="panel-title" style="margin:0;">
                    <i class="fa-solid fa-folder-open" style="color:#10b981;margin-right:8px;"></i>
                    Editar Directorio Raíz: <span style="color:var(--accent-light);">{{ $ftpForPath?->username }}</span>
                </h3>
                <button wire:click="$set('editPathId', null)" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="lp-modal-body">
                <div class="form-group">
                    <label class="form-label">Nuevo Directorio de Inicio (relativo al dominio)</label>
                    <div style="display:flex;align-items:center;background:rgba(255,255,255,0.05);border:1px solid var(--glass-border);border-radius:8px;overflow:hidden;">
                        <span style="padding:10px 14px;background:rgba(255,255,255,0.05);color:var(--text-muted);font-size:13px;border-right:1px solid var(--glass-border);font-family:monospace;">
                            /
                        </span>
                        <input type="text" wire:model="editPath" class="form-input" style="border:none;background:none;margin:0;" placeholder="ej. public_html o dejar vacío para la raíz">
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                        También puedes escribir una ruta absoluta empezando por `/` (ej. `/var/www`).
                    </div>
                    @error('editPath') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="lp-modal-footer">
                <button wire:click="$set('editPathId', null)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="savePathEdit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Change Password Modal --}}
    @if($changingPasswordId)

    @php
        $ftpToChange = $ftps->firstWhere('id', $changingPasswordId);
    @endphp
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated" style="max-width:440px;">
            <div class="lp-modal-header">
                <h3 class="panel-title" style="margin:0;">
                    <i class="fa-solid fa-key" style="color:var(--warning);margin-right:8px;"></i>
                    Cambiar Contraseña FTP
                </h3>
                <button wire:click="$set('changingPasswordId', null)" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="lp-modal-body">
                <p style="color:var(--text-secondary);font-size:12px;margin-bottom:16px;">
                    Actualizar contraseña para el usuario FTP: <strong style="color:var(--text-primary);">{{ $ftpToChange?->username }}</strong>
                </p>

                <div class="form-group">
                <label class="form-label">Nueva Contraseña</label>
                <div style="display:flex;gap:8px;">
                    <input type="text" wire:model="newPassword" class="form-input" placeholder="Nueva contraseña segura" style="margin-bottom:0;" autofocus>
                    <button type="button" wire:click="generateRandomNewPassword" class="btn btn-ghost" style="flex-shrink:0;">
                        Generar
                    </button>
                </div>
                @error('newPassword') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="lp-modal-footer">
                <button wire:click="$set('changingPasswordId', null)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="changePassword" class="btn btn-primary btn-sm" style="background:var(--warning);border-color:var(--warning);color:black;">
                    Actualizar Contraseña
                </button>
            </div>
        </div>
    </div>
    @endif

    <div wire:loading class="lp-loading-toast">
        <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
    </div>
</div>
