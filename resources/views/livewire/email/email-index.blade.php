<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Cuentas de Correo Electrónico</h1>
            <p class="page-subtitle">
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

    <div class="lp-two-col">

        {{-- Creation Panel --}}
        <div class="glass lp-panel">
            <h2 class="panel-title">
                <i class="fa-solid fa-envelope" style="color:var(--accent-light);"></i>
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
                    @error('username') <div class="form-error">{{ $message }}</div> @enderror
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

                {{-- Quota MB --}}
                <div class="form-group">
                    <label class="form-label">Cuota de Almacenamiento (MB)</label>
                    <input type="number" wire:model="quotaMb" class="form-input" placeholder="500">
                    @error('quotaMb') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">
                    <i class="fa-solid fa-plus-circle"></i> Crear Cuenta
                </button>
            </form>
        </div>

        {{-- Email Accounts List --}}
        <div class="glass lp-panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:10px;flex-wrap:wrap;">
                <h2 class="panel-title" style="margin:0;">
                    <i class="fa-solid fa-list" style="color:var(--accent-light);"></i>
                    Cuentas de Correo Activas
                </h2>
                <div style="display:flex;gap:8px;align-items:center;">
                    {{-- Buscador --}}
                    <div class="lp-search">
                        <i class="fa-solid fa-magnifying-glass lp-search-icon"></i>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Buscar correo...">
                    </div>
                    <button wire:click="openImportModal" class="btn btn-ghost btn-sm" style="color:var(--accent-light);">
                        <i class="fa-solid fa-file-zipper"></i> Importar ZIP
                    </button>
                </div>
            </div>

            @if($emailsByDomain->isEmpty())
            <div style="text-align:center;padding:60px 20px;color:var(--text-secondary);">
                <i class="fa-solid fa-envelope-open" style="font-size:40px;opacity:0.25;margin-bottom:14px;display:block;"></i>
                @if($search)
                    No se encontraron cuentas que coincidan con "<strong>{{ $search }}</strong>".
                @else
                    No tiene cuentas de correo configuradas en este momento.
                @endif
            </div>
            @else
                @foreach($emailsByDomain as $domainId => $domainEmails)
                @php $domainName = $domainEmails->first()?->domain?->name ?? 'Sin dominio'; @endphp

                <div x-data="{ open: true }" style="margin-bottom: 12px; border: 1px solid var(--glass-border); border-radius: 8px; overflow: hidden; background: rgba(0,0,0,0.15);">
                    {{-- Domain header --}}
                    <div @click="open = !open" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:rgba(255,255,255,0.02);cursor:pointer;user-select:none;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <i class="fa-solid fa-at" style="color:var(--accent-light);font-size:14px;"></i>
                            <span style="font-size:14px;font-weight:700;color:var(--accent-light);">{{ $domainName }}</span>
                            <span style="font-size:11px;color:var(--text-muted);background:rgba(99,102,241,0.1);padding:2px 8px;border-radius:20px;">{{ $domainEmails->count() }} cuenta(s)</span>
                        </div>
                        <div>
                            <i class="fa-solid fa-chevron-down" x-show="!open" style="color:var(--text-muted);font-size:12px;"></i>
                            <i class="fa-solid fa-chevron-up" x-show="open" style="color:var(--text-muted);font-size:12px;"></i>
                        </div>
                    </div>

                    <div x-show="open" x-collapse class="table-responsive" style="border-radius:0 0 8px 8px;">
                        <table class="lp-table" style="margin-bottom:0;border-top:1px solid var(--glass-border);">
                            <thead>
                                <tr>
                                    <th>Cuenta</th>
                                    <th>Uso / Cuota</th>
                                    <th>Estado</th>
                                    <th>Reenvíos</th>
                                    <th style="text-align:right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($domainEmails as $email)
                                <tr>
                                    <td>
                                        <strong style="color:var(--text-primary);font-size:13px;">{{ $email->email }}</strong>
                                    </td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <span style="font-size:11px;font-weight:500;white-space:nowrap;">{{ $email->usedFormatted() }} / {{ $email->quotaFormatted() }}</span>
                                            @php $percent = $email->quota_bytes > 0 ? ($email->used_bytes / $email->quota_bytes) * 100 : 0; @endphp
                                            <div style="width:40px;height:5px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden;flex-shrink:0;">
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
                                        <span style="color:var(--text-muted);font-size:11px;">Ninguno</span>
                                        @else
                                        <span class="badge badge-accent" style="font-size:11px;">{{ count($email->forwarders) }} destino(s)</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right;">
                                        <div class="lp-row-actions">
                                            {{-- Webmail auto-login --}}
                                            <button wire:click="openWebmail({{ $email->id }})" class="btn btn-ghost btn-sm" title="Abrir Webmail">
                                                <i class="fa-solid fa-envelope-open-text" style="color:#10b981;"></i>
                                            </button>
                                            {{-- Backup --}}
                                            <a href="{{ route('email.backup', $email->id) }}" class="btn btn-ghost btn-sm" title="Descargar Respaldo" target="_blank">
                                                <i class="fa-solid fa-floppy-disk" style="color:var(--warning);"></i>
                                            </a>
                                            {{-- Forwarders --}}
                                            <button wire:click="editForwarders({{ $email->id }})" class="btn btn-ghost btn-sm" title="Redirecciones">
                                                <i class="fa-solid fa-route" style="color:var(--accent-light);"></i>
                                            </button>
                                            {{-- Change Password --}}
                                            <button wire:click="confirmChangePassword({{ $email->id }})" class="btn btn-ghost btn-sm" title="Cambiar Contraseña">
                                                <i class="fa-solid fa-key" style="color:var(--text-muted);"></i>
                                            </button>
                                            {{-- Delete --}}
                                            <button wire:click="deleteEmail({{ $email->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que desea eliminar esta cuenta de correo?')" title="Eliminar">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endforeach
            @endif
        </div>

    </div>

    {{-- Change Password Modal --}}
    @if($changingPasswordId)
    @php $acctToChange = $emailsByDomain->flatten()->firstWhere('id', $changingPasswordId); @endphp
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated">
            <div class="lp-modal-header">
                <h3 class="panel-title" style="margin:0;">
                    <i class="fa-solid fa-key" style="color:var(--warning);"></i>
                    Cambiar Contraseña Correo
                </h3>
                <button wire:click="$set('changingPasswordId', null)" class="lp-modal-close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="lp-modal-body">
                <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px;">
                    Actualizar contraseña para: <strong style="color:var(--text-primary);">{{ $acctToChange?->email }}</strong>
                </p>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Nueva Contraseña</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" wire:model="newPassword" class="form-input" placeholder="Nueva contraseña segura" style="margin-bottom:0;" autofocus>
                        <button type="button" wire:click="generateRandomNewPassword" class="btn btn-ghost" style="flex-shrink:0;">Generar</button>
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

    {{-- Edit Forwarders Modal --}}
    @if($editingForwardersId)
    @php $acctToForward = $emailsByDomain->flatten()->firstWhere('id', $editingForwardersId); @endphp
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated">
            <div class="lp-modal-header">
                <h3 class="panel-title" style="margin:0;">
                    <i class="fa-solid fa-route" style="color:var(--accent-light);"></i>
                    Redirecciones de Correo
                </h3>
                <button wire:click="$set('editingForwardersId', null)" class="lp-modal-close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="lp-modal-body">
                <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px;">
                    Configure correos externos donde se reenviarán los correos recibidos en <strong style="color:var(--text-primary);">{{ $acctToForward?->email }}</strong>.
                </p>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Direcciones de Destino (separadas por coma)</label>
                    <textarea wire:model="forwarderInput" class="form-input" style="height:100px;font-family:monospace;" placeholder="ej. usuario@gmail.com, admin@empresa.com" autofocus></textarea>
                    @error('forwarderInput') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="lp-modal-footer">
                <button wire:click="$set('editingForwardersId', null)" class="btn btn-ghost">Cancelar</button>
                <button wire:click="saveForwarders" class="btn btn-primary">Guardar Destinos</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Import Modal --}}
    @if($showImportModal)
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated">
            <div class="lp-modal-header">
                <h3 class="panel-title" style="margin:0;">
                    <i class="fa-solid fa-file-zipper" style="color:var(--accent-light);"></i>
                    Importar Correos (ZIP)
                </h3>
                <button wire:click="closeImportModal" class="lp-modal-close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form wire:submit.prevent="importFromZip" style="display:contents;">
                <div class="lp-modal-body">
                    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px;">
                        Sube un archivo <code>.zip</code> (ej. de cPanel) que contenga las carpetas Maildir de los usuarios.
                    </p>
                    <div class="form-group">
                        <label class="form-label">Dominio Destino</label>
                        <select wire:model="importDomainId" class="form-input" required>
                            <option value="">Seleccione dominio...</option>
                            @foreach($domains as $dom)
                            <option value="{{ $dom->id }}">{{ $dom->name }}</option>
                            @endforeach
                        </select>
                        @error('importDomainId') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contraseña por defecto</label>
                        <input type="text" wire:model="defaultImportPassword" class="form-input" placeholder="Para todas las cuentas" required>
                        @error('defaultImportPassword') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Archivo ZIP (máx. 500MB)</label>
                        <input type="file" wire:model="zipFile" class="form-input" accept=".zip" required>
                        @error('zipFile') <div class="form-error">{{ $message }}</div> @enderror
                        <div wire:loading wire:target="zipFile" style="font-size:11px;color:var(--accent-light);margin-top:4px;">
                            <i class="fa-solid fa-spinner fa-spin"></i> Subiendo archivo...
                        </div>
                    </div>
                </div>
                <div class="lp-modal-footer">
                    <button type="button" wire:click="closeImportModal" class="btn btn-ghost">Cancelar</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="importFromZip">
                        <i class="fa-solid fa-upload"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div wire:loading.delay class="lp-loading-toast">
        <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('livewire:initialized', () => {
    Livewire.on('open-url', (data) => {
        window.open(data.url, '_blank');
    });
});
</script>
@endpush
