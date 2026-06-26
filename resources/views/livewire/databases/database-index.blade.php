<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Bases de Datos MySQL</h1>
            <p class="page-subtitle">
                Cree esquemas de base de datos y usuarios con privilegios completos para sus aplicaciones web.
            </p>
        </div>
        <div style="display:flex;gap:10px;">
            @if($hasPma)
            <a href="{{ route('admin.db') }}" target="_blank" class="btn btn-primary" style="background:var(--success);border-color:var(--success);">
                <i class="fa-solid fa-external-link-alt"></i> Abrir phpMyAdmin
            </a>
            @else
            <button wire:click="installPhpMyAdmin" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="installPhpMyAdmin"><i class="fa-solid fa-download"></i> Instalar phpMyAdmin</span>
                <span wire:loading wire:target="installPhpMyAdmin"><i class="fa-solid fa-spinner fa-spin"></i> Instalando... (puede tardar)</span>
            </button>
            @endif
            <button wire:click="refreshSizes" class="btn btn-ghost">
                <i class="fa-solid fa-sync fa-spin-hover"></i> Actualizar Tamaños
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

    <div class="lp-two-col">
        
        {{-- Creation Panel --}}
        <div class="glass lp-panel">
            <h2 class="panel-title">
                <i class="fa-solid fa-database" style="color:var(--accent-light);"></i>
                Crear Base de Datos
            </h2>
            
            <form wire:submit.prevent="createDatabase">
                {{-- DB Name --}}
                <div class="form-group">
                    <label class="form-label">Nombre de Base de Datos</label>
                    <div class="input-prefix-wrap">
                        <span class="input-prefix">{{ $dbPrefix }}</span>
                        <input type="text" wire:model="dbNameSuffix" class="form-input" placeholder="dbname">
                    </div>
                    @error('dbNameSuffix') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                {{-- DB User --}}
                <div class="form-group">
                    <label class="form-label">Usuario de la Base de Datos</label>
                    <div class="input-prefix-wrap">
                        <span class="input-prefix">{{ $dbPrefix }}</span>
                        <input type="text" wire:model="dbUserSuffix" class="form-input" placeholder="dbuser">
                    </div>
                    @error('dbUserSuffix') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                {{-- Password --}}
                <div class="form-group">
                    <label class="form-label">Contraseña del Usuario</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" wire:model="dbPassword" class="form-input" placeholder="Contraseña segura" style="margin-bottom:0;">
                        <button type="button" wire:click="generateRandomPassword" class="btn btn-ghost" style="flex-shrink:0;">
                            Generar
                        </button>
                    </div>
                    @error('dbPassword') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                {{-- Associated Domain --}}
                <div class="form-group">
                    <label class="form-label">Vincular a Dominio <span style="font-weight:400;color:var(--text-muted);">— opcional</span></label>
                    <select wire:model="domainId" class="form-input">
                        <option value="">Ninguno</option>
                        @foreach($domains as $domain)
                        <option value="{{ $domain->id }}">{{ $domain->name }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">
                    <i class="fa-solid fa-plus-circle"></i> Crear Base y Usuario
                </button>
            </form>
        </div>

        {{-- Databases List Panel --}}
        <div class="glass lp-panel">
            <h2 class="panel-title">
                <i class="fa-solid fa-list" style="color:var(--accent-light);"></i>
                Bases de Datos Activas
            </h2>

            @if($databases->isEmpty())
            <div style="text-align:center;padding:60px 20px;color:var(--text-secondary);">
                <i class="fa-solid fa-database" style="font-size:40px;opacity:0.25;margin-bottom:14px;display:block;"></i>
                No tiene bases de datos creadas. Use el formulario de la izquierda para comenzar.
            </div>
            @else
            <div class="table-responsive">
                <table class="lp-table">
                    <thead>
                        <tr>
                            <th>Base de Datos</th>
                            <th>Usuario</th>
                            <th>Host / Puerto</th>
                            <th>Tamaño</th>
                            <th>Dominio Vinculado</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($databases as $db)
                        <tr>
                            <td>
                                <strong style="color:var(--text-primary);">{{ $db->db_name }}</strong>
                            </td>
                            <td>
                                <code style="font-size:12px;background:rgba(255,255,255,0.06);padding:2px 6px;border-radius:4px;border:1px solid var(--glass-border);">{{ $db->db_user }}</code>
                            </td>
                            <td>
                                <span style="font-size:12px;color:var(--text-secondary);">{{ $db->db_host }}:{{ $db->db_port }}</span>
                            </td>
                            <td>
                                <span style="font-size:12px;font-weight:500;">{{ $db->sizeFormatted() }}</span>
                            </td>
                            <td>
                                @if($db->domain)
                                <span class="badge badge-accent" style="font-size:11px;">{{ $db->domain->name }}</span>
                                @else
                                <span style="color:var(--text-muted);font-size:11px;">—</span>
                                @endif
                            </td>
                            <td style="text-align:right;">
                                <div class="lp-row-actions">
                                    <button wire:click="exportDatabase({{ $db->id }})" class="btn btn-ghost btn-sm" title="Exportar SQL (.sql)">
                                        <i class="fa-solid fa-file-export" style="color:var(--success);"></i>
                                    </button>
                                    <button wire:click="openImport({{ $db->id }})" class="btn btn-ghost btn-sm" title="Importar SQL">
                                        <i class="fa-solid fa-file-import" style="color:var(--accent-light);"></i>
                                    </button>
                                    <button wire:click="confirmChangePassword({{ $db->id }})" class="btn btn-ghost btn-sm" title="Cambiar Contraseña">
                                        <i class="fa-solid fa-key" style="color:var(--warning);"></i>
                                    </button>
                                    <button wire:click="confirmDelete({{ $db->id }})" class="btn btn-danger btn-sm" title="Eliminar Base de Datos">
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
        $dbToChange = $databases->firstWhere('id', $changingPasswordId);
    @endphp
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated">
            <div class="lp-modal-header">
                <h3 class="panel-title">
                    <i class="fa-solid fa-key" style="color:var(--warning);"></i>
                    Cambiar Contraseña
                </h3>
                <button wire:click="$set('changingPasswordId', null)" class="lp-modal-close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="lp-modal-body">
                <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px;">
                    Actualizar contraseña de usuario para: <strong style="color:var(--text-primary);">{{ $dbToChange?->db_user }}</strong>
                </p>

                <div class="form-group">
                    <label class="form-label">Nueva Contraseña</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" wire:model="newPassword" class="form-input" placeholder="Nueva contraseña segura" style="margin-bottom:0;">
                        <button type="button" wire:click="generateRandomNewPassword" class="btn btn-ghost" style="flex-shrink:0;">
                            Generar
                        </button>
                    </div>
                    @error('newPassword') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="lp-modal-footer">
                <button wire:click="$set('changingPasswordId', null)" class="btn btn-ghost">Cancelar</button>
                <button wire:click="changePassword" class="btn btn-primary" style="background:var(--warning);border-color:var(--warning);color:black;">
                    Actualizar Contraseña
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Delete Confirmation Modal --}}
    @if($deletingId)
    @php
        $dbToDelete = $databases->firstWhere('id', $deletingId);
    @endphp
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated" style="text-align:center;">
            <div class="lp-modal-body" style="padding-top:32px;">
                <div style="width:52px;height:52px;border-radius:50%;background:rgba(239,68,68,0.15);border:2px solid rgba(239,68,68,0.3);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                    <i class="fa-solid fa-triangle-exclamation" style="color:var(--danger);font-size:20px;"></i>
                </div>
                <h2 style="font-size:17px;font-weight:700;margin-bottom:8px;">¿Eliminar Base de Datos?</h2>
                <p style="color:var(--text-secondary);font-size:13px;margin-bottom:22px;line-height:1.6;">
                    Esta acción eliminará de forma irreversible el esquema <strong style="color:var(--text-primary);">{{ $dbToDelete?->db_name }}</strong> y el usuario de base de datos asociado. Todos los datos se perderán para siempre.
                </p>
                <div style="display:flex;gap:10px;">
                    <button wire:click="$set('deletingId', null)" class="btn btn-ghost" style="flex:1;justify-content:center;">Cancelar</button>
                    <button wire:click="deleteDatabase" class="btn btn-danger" style="flex:1;justify-content:center;">
                        <i class="fa-solid fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Import SQL Modal --}}
    @if($importingId)
    @php
        $dbToImport = $databases->firstWhere('id', $importingId);
    @endphp
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated">
            <div class="lp-modal-header">
                <h3 class="panel-title">
                    <i class="fa-solid fa-file-import" style="color:var(--accent-light);"></i>
                    Importar SQL
                </h3>
                <button wire:click="$set('importingId', null)" class="lp-modal-close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="lp-modal-body">
                <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px;">
                    Importar archivo SQL en la base de datos: <strong style="color:var(--text-primary);">{{ $dbToImport?->db_name }}</strong>
                </p>
                <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:12px;color:var(--danger);">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    La importación ejecutará el SQL sobre la base de datos existente. Haz un export primero como respaldo.
                </div>
                <div class="form-group">
                    <label class="form-label">Archivo .sql</label>
                    <input type="file" wire:model="importFile" accept=".sql,.txt" class="form-input" style="padding:10px;">
                    @error('importFile') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="lp-modal-footer">
                <button wire:click="$set('importingId', null)" class="btn btn-ghost">Cancelar</button>
                <button wire:click="importDatabase" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove><i class="fa-solid fa-upload"></i> Importar SQL</span>
                    <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Importando...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    <div wire:loading.delay class="lp-loading-toast">
        <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
    </div>
</div>
