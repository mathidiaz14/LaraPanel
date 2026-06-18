<div>
    {{-- Email Submenu Navigation --}}
    @include('livewire.email._email-nav', ['active' => 'aliases'])

    <div class="page-header">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">Aliases y Redirecciones</h1>
            <p style="color:var(--text-secondary);font-size:13px;">Redirige emails a otras cuentas. Los catch-all capturan cualquier dirección del dominio.</p>
        </div>
    </div>

    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start;">

        {{-- Create Alias Form --}}
        <div class="glass" style="padding:24px;">
            <h2 style="font-size:15px;font-weight:700;margin-bottom:16px;">
                <i class="fa-solid fa-at" style="color:var(--accent-light);margin-right:8px;"></i>
                Nuevo Alias
            </h2>

            <form wire:submit.prevent="createAlias">
                <div class="form-group">
                    <label class="form-label">Dominio</label>
                    <select wire:model.live="domainId" class="form-input">
                        <option value="">Seleccione dominio...</option>
                        @foreach($domains as $domain)
                        <option value="{{ $domain->id }}">{{ $domain->name }}</option>
                        @endforeach
                    </select>
                    @error('domainId') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
                </div>

                {{-- Catch-all toggle --}}
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" wire:model.live="isCatchall" style="accent-color:var(--accent);">
                        <span style="font-size:13px;font-weight:600;">Catch-All</span>
                        <span style="font-size:11px;color:var(--text-muted);">(captura todo el dominio)</span>
                    </label>
                </div>

                @if(!$isCatchall)
                <div class="form-group">
                    <label class="form-label">Alias (parte antes de @)</label>
                    @php $selectedDomainName = $domains->firstWhere('id', $domainId)?->name ?? 'dominio.com'; @endphp
                    <div style="display:flex;align-items:center;gap:0;">
                        <input type="text" wire:model="sourcePrefix" class="form-input" placeholder="info" style="border-radius:8px 0 0 8px;flex:1;">
                        <div style="background:rgba(255,255,255,0.06);border:1px solid var(--glass-border);border-left:none;padding:0 12px;height:40px;display:flex;align-items:center;font-size:12px;color:var(--text-secondary);border-radius:0 8px 8px 0;white-space:nowrap;">
                            @{{ $selectedDomainName }}
                        </div>
                    </div>
                </div>
                @else
                <div style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:12px;color:var(--text-secondary);">
                    <i class="fa-solid fa-star" style="color:var(--accent-light);"></i>
                    El alias catch-all capturará <strong>todos los correos</strong> enviados a cualquier dirección de <strong>{{ $domains->firstWhere('id', $domainId)?->name ?? 'este dominio' }}</strong> que no tengan buzón propio.
                </div>
                @endif

                <div class="form-group">
                    <label class="form-label">Redirigir a (separar con coma)</label>
                    <input type="text" wire:model="destinations" class="form-input" placeholder="real@gmail.com, otro@empresa.com">
                    @error('destinations') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Puede redirigir a múltiples destinatarios.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notas <span style="color:var(--text-muted);font-weight:400;">— opcional</span></label>
                    <input type="text" wire:model="notes" class="form-input" placeholder="ej. Alias para marketing">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;" wire:loading.attr="disabled">
                    <i class="fa-solid fa-plus-circle"></i>
                    {{ $isCatchall ? 'Crear Catch-All' : 'Crear Alias' }}
                </button>
            </form>
        </div>

        {{-- Aliases List --}}
        <div class="glass" style="padding:24px;">
            <h2 style="font-size:15px;font-weight:700;margin-bottom:16px;">
                <i class="fa-solid fa-list" style="color:var(--accent-light);margin-right:8px;"></i>
                Aliases Configurados
            </h2>

            @if($aliases->isEmpty())
            <div style="text-align:center;padding:40px;color:var(--text-muted);">
                <i class="fa-solid fa-at" style="font-size:36px;opacity:0.2;margin-bottom:12px;display:block;"></i>
                No hay aliases configurados.
            </div>
            @else
            <div style="display:flex;flex-direction:column;gap:10px;">
                @foreach($aliases as $alias)
                <div style="background:rgba(255,255,255,0.04);border:1px solid {{ $alias->is_catchall ? 'rgba(99,102,241,0.3)' : 'var(--glass-border)' }};border-radius:10px;padding:14px 16px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            @if($alias->is_catchall)
                            <span style="background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.3);border-radius:6px;padding:2px 8px;font-size:10px;font-weight:700;color:var(--accent-light);">CATCH-ALL</span>
                            @endif
                            <code style="font-size:13px;font-weight:600;color:var(--text-primary);">{{ $alias->source }}</code>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span class="badge {{ $alias->is_active ? 'badge-success' : 'badge-muted' }}" style="font-size:10px;cursor:pointer;" wire:click="toggleAlias({{ $alias->id }})">
                                {{ $alias->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                    </div>

                    @if($editingId === $alias->id)
                    <div style="margin-bottom:10px;">
                        <input type="text" wire:model="editDestinations" class="form-input" style="font-size:12px;" placeholder="email1@dom.com, email2@dom.com">
                        <div style="display:flex;gap:6px;margin-top:8px;">
                            <button wire:click="saveEdit" class="btn btn-primary btn-sm">Guardar</button>
                            <button wire:click="$set('editingId', null)" class="btn btn-ghost btn-sm">Cancelar</button>
                        </div>
                    </div>
                    @else
                    <div style="font-size:12px;color:var(--text-secondary);margin-bottom:8px;">
                        <i class="fa-solid fa-arrow-right" style="color:var(--success);margin-right:6px;"></i>
                        {{ $alias->destinationsFormatted() }}
                    </div>
                    @endif

                    @if($alias->notes && $editingId !== $alias->id)
                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;">{{ $alias->notes }}</div>
                    @endif

                    <div style="display:flex;gap:6px;justify-content:flex-end;">
                        <button wire:click="openEdit({{ $alias->id }})" class="btn btn-ghost btn-sm" title="Editar destinos">
                            <i class="fa-solid fa-pen" style="color:var(--accent-light);"></i>
                        </button>
                        <button wire:click="deleteAlias({{ $alias->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este alias?')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    <div wire:loading style="position:fixed;bottom:24px;right:24px;z-index:300;">
        <div class="glass" style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
        </div>
    </div>
</div>
