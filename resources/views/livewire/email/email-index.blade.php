<div>
    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">Cuentas de Correo Electrónico</h1>
            <p style="color:var(--text-secondary);font-size:13px;">
                Cree y gestione casillas de correo virtuales y redirecciones para sus dominios activos.
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
                <i class="fa-solid fa-envelope" style="color:var(--accent-light);margin-right:8px;"></i>
                Nueva Casilla
            </h2>

            <form wire:submit.prevent="createEmail">
                {{-- Username / Domain --}}
                <div class="form-group">
                    <label class="form-label">Dirección de Correo</label>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <input type="text" wire:model="username" class="form-input" style="margin:0;flex:1;" placeholder="ej. info">
                        <span style="color:var(--text-muted);font-weight:600;">@</span>
                        <select wire:model="domainId" class="form-input" style="margin:0;flex:1.5;">
                            <option value="">Seleccione dominio...</option>
                            @foreach($domains as $dom)
                            <option value="{{ $dom->id }}">{{ $dom->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('username') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
                    @error('domainId') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
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
                    @error('password') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
                </div>

                {{-- Quota MB --}}
                <div class="form-group">
                    <label class="form-label">Cuota de Almacenamiento (MB)</label>
                    <input type="number" wire:model="quotaMb" class="form-input" placeholder="500">
                    @error('quotaMb') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">
                    <i class="fa-solid fa-plus-circle"></i> Crear Cuenta
                </button>
            </form>
        </div>

        {{-- Email Accounts List --}}
        <div class="glass" style="padding:24px;">
        <div class="glass" style="padding:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h2 style="font-size:15px;font-weight:700;color:var(--text-primary);margin:0;">
                    <i class="fa-solid fa-list" style="color:var(--accent-light);margin-right:8px;"></i>
                    Cuentas de Correo Activas
                </h2>
                <button wire:click="openImportModal" class="btn btn-ghost btn-sm" style="color:var(--accent-light);">
                    <i class="fa-solid fa-file-zipper"></i> Importar desde ZIP
                </button>
            </div>

            @if($emails->isEmpty())
            <div style="text-align:center;padding:60px 20px;color:var(--text-secondary);">
                <i class="fa-solid fa-envelope-open" style="font-size:40px;opacity:0.25;margin-bottom:14px;display:block;"></i>
                No tiene cuentas de correo configuradas en este momento.
            </div>
            @else
            <div style="overflow-x:auto;">
                <table class="table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Cuenta de Correo</th>
                            <th>Uso / Cuota</th>
                            <th>Estado</th>
                            <th>Redirecciones</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($emails as $email)
                        <tr>
                            <td>
                                <strong style="color:var(--text-primary);">{{ $email->email }}</strong>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span style="font-size:12px;font-weight:500;">{{ $email->usedFormatted() }} / {{ $email->quotaFormatted() }}</span>
                                    @php
                                        $percent = $email->quota_bytes > 0 ? ($email->used_bytes / $email->quota_bytes) * 100 : 0;
                                    @endphp
                                    <div style="width:50px;height:6px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden;">
                                        <div style="width:{{ min(100, $percent) }}%;height:100%;background:{{ $percent > 85 ? 'var(--danger)' : 'var(--accent-light)' }};"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span wire:click="toggleStatus({{ $email->id }})" class="badge {{ $email->is_active ? 'badge-success' : 'badge-danger' }}" style="cursor:pointer;font-size:11px;">
                                    {{ $email->is_active ? 'Activo' : 'Suspendido' }}
                                </span>
                            </td>
                            <td>
                                @if(empty($email->forwarders))
                                <span style="color:var(--text-muted);font-size:11px;">Ninguna</span>
                                @else
                                <span class="badge badge-accent" style="font-size:11px;">{{ count($email->forwarders) }} destino(s)</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:6px;">
                                    <button wire:click="editForwarders({{ $email->id }})" class="btn btn-ghost btn-sm" title="Redirecciones">
                                        <i class="fa-solid fa-route" style="color:var(--accent-light);"></i>
                                    </button>
                                    <button wire:click="confirmChangePassword({{ $email->id }})" class="btn btn-ghost btn-sm" title="Cambiar Contraseña">
                                        <i class="fa-solid fa-key" style="color:var(--warning);"></i>
                                    </button>
                                    <button wire:click="deleteEmail({{ $email->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que desea eliminar esta cuenta de correo?')" title="Eliminar Cuenta">
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

    {{-- Change Password Modal --}}
    @if($changingPasswordId)
    @php
        $acctToChange = $emails->firstWhere('id', $changingPasswordId);
    @endphp
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="max-width:440px;width:100%;padding:28px;margin:16px;">
            <div style="display:flex;justify-content:between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;">
                <h3 style="font-size:16px;font-weight:700;margin:0;">
                    <i class="fa-solid fa-key" style="color:var(--warning);margin-right:8px;"></i>
                    Cambiar Contraseña Correo
                </h3>
                <button wire:click="$set('changingPasswordId', null)" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;margin-left:auto;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <p style="color:var(--text-secondary);font-size:12px;margin-bottom:16px;">
                Actualizar contraseña para: <strong style="color:var(--text-primary);">{{ $acctToChange?->email }}</strong>
            </p>

            <div class="form-group">
                <label class="form-label">Nueva Contraseña</label>
                <div style="display:flex;gap:8px;">
                    <input type="text" wire:model="newPassword" class="form-input" placeholder="Nueva contraseña segura" style="margin-bottom:0;" autofocus>
                    <button type="button" wire:click="generateRandomNewPassword" class="btn btn-ghost" style="flex-shrink:0;">
                        Generar
                    </button>
                </div>
                @error('newPassword') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button wire:click="$set('changingPasswordId', null)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="changePassword" class="btn btn-primary btn-sm" style="background:var(--warning);border-color:var(--warning);color:black;">
                    Actualizar Contraseña
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Edit Forwarders Modal --}}
    @if($editingForwardersId)
    @php
        $acctToForward = $emails->firstWhere('id', $editingForwardersId);
    @endphp
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="max-width:440px;width:100%;padding:28px;margin:16px;">
            <div style="display:flex;justify-content:between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;">
                <h3 style="font-size:16px;font-weight:700;margin:0;">
                    <i class="fa-solid fa-route" style="color:var(--accent-light);margin-right:8px;"></i>
                    Redirecciones de Correo
                </h3>
                <button wire:click="$set('editingForwardersId', null)" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;margin-left:auto;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <p style="color:var(--text-secondary);font-size:12px;margin-bottom:16px;">
                Configure correos externos donde se reenviarán los correos recibidos en <strong style="color:var(--text-primary);">{{ $acctToForward?->email }}</strong>.
            </p>

            <div class="form-group">
                <label class="form-label">Direcciones de Destino (separadas por coma)</label>
                <textarea wire:model="forwarderInput" class="form-input" style="height:100px;font-family:monospace;" placeholder="ej. usuario@gmail.com, admin@empresa.com" autofocus></textarea>
                @error('forwarderInput') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button wire:click="$set('editingForwardersId', null)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="saveForwarders" class="btn btn-primary btn-sm">Guardar Destinos</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Import Modal --}}
    @if($showImportModal)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="max-width:440px;width:100%;padding:28px;margin:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;">
                <h3 style="font-size:16px;font-weight:700;margin:0;">
                    <i class="fa-solid fa-file-zipper" style="color:var(--accent-light);margin-right:8px;"></i>
                    Importar Correos (ZIP)
                </h3>
                <button wire:click="closeImportModal" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;margin-left:auto;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <p style="color:var(--text-secondary);font-size:12px;margin-bottom:16px;">
                Sube un archivo <code>.zip</code> (ej. de cPanel) que contenga las carpetas Maildir de los usuarios. Las cuentas se crearán automáticamente y los correos se migrarán.
            </p>

            <form wire:submit.prevent="importFromZip">
                <div class="form-group">
                    <label class="form-label">Dominio Destino</label>
                    <select wire:model="importDomainId" class="form-input" required>
                        <option value="">Seleccione dominio...</option>
                        @foreach($domains as $dom)
                        <option value="{{ $dom->id }}">{{ $dom->name }}</option>
                        @endforeach
                    </select>
                    @error('importDomainId') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Contraseña por defecto</label>
                    <input type="text" wire:model="defaultImportPassword" class="form-input" placeholder="Para todas las cuentas" required>
                    <span style="font-size:11px;color:var(--text-muted);">Se asignará a todas las cuentas importadas.</span>
                    @error('defaultImportPassword') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Archivo ZIP (máx. 500MB)</label>
                    <input type="file" wire:model="zipFile" class="form-input" accept=".zip" required>
                    @error('zipFile') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
                    
                    <div wire:loading wire:target="zipFile" style="font-size:11px;color:var(--accent-light);margin-top:4px;">
                        <i class="fa-solid fa-spinner fa-spin"></i> Subiendo archivo...
                    </div>
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                    <button type="button" wire:click="closeImportModal" class="btn btn-ghost btn-sm">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="importFromZip">
                        <i class="fa-solid fa-upload"></i> Comenzar Importación
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div wire:loading style="position:fixed;bottom:24px;right:24px;z-index:300;">
        <div class="glass" style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
        </div>
    </div>
</div>
