<div>
    @include('livewire.email._email-nav', ['active' => 'autoresponders'])

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">Autoresponders</h1>
            <p style="color:var(--text-secondary);font-size:13px;">Configure respuestas automáticas para períodos de vacaciones o fuera de oficina.</p>
        </div>
    </div>

    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    <div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start;">

        {{-- Account Selector --}}
        <div class="glass" style="padding:20px;">
            <h2 style="font-size:14px;font-weight:700;margin-bottom:14px;">Seleccionar Buzón</h2>
            @if($accounts->isEmpty())
            <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:12px;">
                No hay buzones activos. Crea uno primero en <a href="{{ route('email.index') }}" style="color:var(--accent-light);">Buzones</a>.
            </div>
            @else
            @foreach($accounts as $account)
            <button wire:click="selectAccount({{ $account->id }})"
                style="width:100%;text-align:left;background:{{ $selectedAccountId === $account->id ? 'rgba(99,102,241,0.15)' : 'rgba(255,255,255,0.04)' }};border:1px solid {{ $selectedAccountId === $account->id ? 'rgba(99,102,241,0.4)' : 'var(--glass-border)' }};border-radius:8px;padding:10px 12px;cursor:pointer;margin-bottom:6px;display:flex;align-items:center;gap:8px;transition:all 0.2s;">
                <i class="fa-solid fa-inbox" style="color:{{ $selectedAccountId === $account->id ? 'var(--accent-light)' : 'var(--text-muted)' }};"></i>
                <div>
                    <div style="font-size:12px;font-weight:600;color:var(--text-primary);">{{ $account->email }}</div>
                    <div style="font-size:10px;color:var(--text-muted);">{{ $account->domain->name ?? '' }}</div>
                </div>
            </button>
            @endforeach
            @endif
        </div>

        @if($selectedAccountId)
        <div style="display:flex;flex-direction:column;gap:16px;">

            {{-- Form --}}
            <div class="glass" style="padding:22px;">
                <h2 style="font-size:14px;font-weight:700;margin-bottom:16px;">
                    <i class="fa-solid fa-reply" style="color:var(--accent-light);margin-right:6px;"></i>
                    {{ $editingId ? 'Editar Autoresponder' : 'Nuevo Autoresponder' }}
                </h2>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Asunto del mensaje</label>
                        <input type="text" wire:model="subject" class="form-input" placeholder="Estoy fuera de la oficina">
                        @error('subject') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Responder desde (opcional)</label>
                        <input type="email" wire:model="replyFrom" class="form-input" placeholder="noreply@midominio.com">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mensaje de respuesta</label>
                    <textarea wire:model="body" class="form-input" style="height:120px;resize:vertical;" placeholder="Hola, estoy de vacaciones hasta el [fecha]. Responderé a tu mensaje cuando regrese..."></textarea>
                    @error('body') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:14px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Activo desde (opcional)</label>
                        <input type="date" wire:model="startsAt" class="form-input" style="font-size:12px;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Activo hasta (opcional)</label>
                        <input type="date" wire:model="endsAt" class="form-input" style="font-size:12px;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Repetir cada (días)</label>
                        <input type="number" wire:model="repeatIntervalDays" class="form-input" min="1" max="30" style="font-size:12px;">
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" wire:model="isActive" style="accent-color:var(--accent);width:16px;height:16px;">
                        <span style="font-size:13px;font-weight:600;">Activar autoresponder</span>
                    </label>
                    <div style="display:flex;gap:8px;">
                        @if($editingId)
                        <button wire:click="cancelEdit" class="btn btn-ghost btn-sm">Cancelar</button>
                        @endif
                        <button wire:click="saveAutoresponder" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                            <span wire:loading.remove>
                                <i class="fa-solid fa-{{ $editingId ? 'floppy-disk' : 'plus-circle' }}"></i>
                                {{ $editingId ? 'Guardar Cambios' : 'Crear Autoresponder' }}
                            </span>
                            <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Guardando...</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- List --}}
            @if($autoresponders->isNotEmpty())
            <div class="glass" style="padding:20px;">
                <h2 style="font-size:14px;font-weight:700;margin-bottom:14px;">
                    <i class="fa-solid fa-list" style="color:var(--accent-light);margin-right:6px;"></i>
                    Autoresponders Configurados ({{ $autoresponders->count() }})
                </h2>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    @foreach($autoresponders as $ar)
                    <div style="background:rgba(255,255,255,0.04);border:1px solid {{ $ar->isCurrentlyActive() ? 'rgba(16,185,129,0.25)' : 'var(--glass-border)' }};border-radius:10px;padding:14px 16px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="badge {{ $ar->isCurrentlyActive() ? 'badge-success' : 'badge-muted' }}" style="font-size:10px;">
                                    {{ $ar->isCurrentlyActive() ? '● Activo ahora' : ($ar->is_active ? '● Programado' : '○ Inactivo') }}
                                </span>
                                <strong style="font-size:13px;color:var(--text-primary);">{{ $ar->subject }}</strong>
                            </div>
                            <div style="display:flex;gap:6px;">
                                <button wire:click="toggleAutoresponder({{ $ar->id }})" class="btn btn-ghost btn-sm" title="{{ $ar->is_active ? 'Desactivar' : 'Activar' }}">
                                    <i class="fa-solid fa-{{ $ar->is_active ? 'pause' : 'play' }}" style="color:var(--{{ $ar->is_active ? 'warning' : 'success' }});"></i>
                                </button>
                                <button wire:click="editAutoresponder({{ $ar->id }})" class="btn btn-ghost btn-sm">
                                    <i class="fa-solid fa-pen" style="color:var(--accent-light);"></i>
                                </button>
                                <button wire:click="deleteAutoresponder({{ $ar->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este autoresponder?')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div style="font-size:12px;color:var(--text-secondary);line-height:1.6;">
                            <div style="white-space:pre-line;max-height:60px;overflow:hidden;text-overflow:ellipsis;">{{ Str::limit($ar->body, 180) }}</div>
                        </div>
                        @if($ar->starts_at || $ar->ends_at)
                        <div style="margin-top:8px;font-size:11px;color:var(--text-muted);">
                            <i class="fa-solid fa-calendar" style="margin-right:4px;"></i>
                            {{ $ar->starts_at?->format('d/m/Y') ?? '∞' }} → {{ $ar->ends_at?->format('d/m/Y') ?? '∞' }}
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
        @else
        <div class="glass" style="padding:60px;text-align:center;">
            <i class="fa-solid fa-reply" style="font-size:40px;opacity:0.2;margin-bottom:12px;display:block;"></i>
            <p style="color:var(--text-secondary);">Selecciona un buzón de la lista izquierda para gestionar sus autoresponders.</p>
        </div>
        @endif

    </div>

    <div wire:loading style="position:fixed;bottom:24px;right:24px;z-index:300;">
        <div class="glass" style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
        </div>
    </div>
</div>
