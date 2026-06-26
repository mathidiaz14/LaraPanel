<div>
    {{-- Header --}}
    <div class="page-header" style="justify-content: flex-start; gap: 14px;">
        <a href="{{ route('dns.index') }}" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title">{{ $zone->name }}</h1>
            <p class="page-subtitle">
                Editor de registros DNS · Tipo: {{ $zone->type }} · Serial: {{ $zone->serial }}
            </p>
        </div>
    </div>

    {{-- Alerts --}}
    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:16px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    {{-- Add Record Form --}}
    <div class="glass lp-panel" style="margin-bottom:20px;">
        <h2 class="panel-title">
            <i class="fa-solid fa-plus" style="color:var(--accent-light);margin-right:6px;"></i>
            Agregar Registro
        </h2>
        <div style="display:grid;grid-template-columns:1.5fr 100px 3fr 80px 80px;gap:10px;align-items:end;">
            <div>
                <label class="form-label" style="font-size:11px;">Nombre</label>
                <input type="text" wire:model="newName" class="form-input" placeholder="@ o subdomain" style="font-family:monospace;font-size:12px;">
                @error('newName') <div class="form-error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="form-label" style="font-size:11px;">Tipo</label>
                <select wire:model.live="newType" class="form-input" style="font-size:12px;">
                    @foreach($recordTypes as $type)
                    <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" style="font-size:11px;">
                    Contenido
                    @if($newType === 'MX') <span style="color:var(--text-muted);">(hostname de destino)</span> @endif
                    @if($newType === 'TXT') <span style="color:var(--text-muted);">(sin comillas)</span> @endif
                </label>
                <input type="text" wire:model="newContent" class="form-input" placeholder="{{ match($newType) { 'A' => '1.2.3.4', 'AAAA' => '2001:db8::1', 'MX' => 'mail.dominio.com', 'CNAME' => 'alias.dominio.com', 'TXT' => 'v=spf1 mx ~all', default => 'valor' } }}" style="font-family:monospace;font-size:12px;">
                @error('newContent') <div class="form-error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="form-label" style="font-size:11px;">TTL</label>
                <select wire:model="newTtl" class="form-input" style="font-size:12px;">
                    <option value="60">60s</option>
                    <option value="300">5m</option>
                    <option value="900">15m</option>
                    <option value="3600" selected>1h</option>
                    <option value="86400">24h</option>
                </select>
            </div>
            <div>
                @if(in_array($newType, ['MX','SRV']))
                <label class="form-label" style="font-size:11px;">Prioridad</label>
                <input type="number" wire:model="newPriority" class="form-input" min="0" max="65535" style="font-size:12px;">
                @else
                <label class="form-label" style="font-size:11px;opacity:0;">-</label>
                <div style="height:40px;"></div>
                @endif
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:12px;gap:8px;">
            <input type="text" wire:model="newComment" class="form-input" placeholder="Comentario opcional" style="flex:1;font-size:12px;">
            <button wire:click="addRecord" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                <span wire:loading.remove><i class="fa-solid fa-plus"></i> Agregar</span>
                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
        </div>
    </div>

    {{-- Records Table --}}
    <div class="glass lp-panel">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h2 class="panel-title" style="margin:0;">
                <i class="fa-solid fa-list" style="color:var(--accent-light);margin-right:6px;"></i>
                Registros DNS ({{ $records->count() }})
            </h2>
        </div>

        @if($records->isEmpty())
        <div style="text-align:center;padding:40px;color:var(--text-muted);">
            <i class="fa-solid fa-database" style="font-size:32px;opacity:0.2;margin-bottom:10px;display:block;"></i>
            Esta zona no tiene registros aún. Agrégalos usando el formulario de arriba.
        </div>
        @else
        <div style="overflow-x:auto;">
            <table class="lp-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Contenido</th>
                        <th>TTL</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th style="text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $record)
                    @if($editingId === $record->id)
                    {{-- Inline Edit Row --}}
                    <tr style="background:rgba(99,102,241,0.06);">
                        <td><input type="text" wire:model="editName" class="form-input" style="font-size:11px;padding:4px 8px;font-family:monospace;"></td>
                        <td><span class="badge {{ $record->typeBadgeClass() }}">{{ $record->type }}</span></td>
                        <td><input type="text" wire:model="editContent" class="form-input" style="font-size:11px;padding:4px 8px;font-family:monospace;"></td>
                        <td><input type="number" wire:model="editTtl" class="form-input" style="font-size:11px;padding:4px 8px;width:70px;"></td>
                        <td><input type="number" wire:model="editPriority" class="form-input" style="font-size:11px;padding:4px 8px;width:60px;"></td>
                        <td><span style="font-size:11px;color:var(--text-muted);">Editando...</span></td>
                        <td style="text-align:right;">
                            <div class="lp-row-actions">
                                <button wire:click="saveEdit" class="btn btn-primary btn-sm" style="padding:4px 8px;">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                                <button wire:click="cancelEdit" class="btn btn-ghost btn-sm" style="padding:4px 8px;">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @else
                    <tr>
                        <td>
                            <code style="font-size:12px;font-family:monospace;color:var(--text-primary);">{{ $record->displayName() }}</code>
                            @if($record->comment)
                            <div style="font-size:10px;color:var(--text-muted);">{{ $record->comment }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $record->typeBadgeClass() }}" style="font-family:monospace;font-size:11px;">{{ $record->type }}</span>
                        </td>
                        <td style="max-width:300px;">
                            <div style="font-size:12px;font-family:monospace;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $record->content }}">
                                {{ $record->content }}
                            </div>
                        </td>
                        <td><span style="font-size:12px;color:var(--text-secondary);">{{ $record->ttl }}s</span></td>
                        <td><span style="font-size:12px;color:var(--text-secondary);">{{ $record->priority ?: '—' }}</span></td>
                        <td>
                            @if($record->is_disabled)
                            <span class="badge badge-danger" style="font-size:10px;">Deshabilitado</span>
                            @else
                            <span class="badge badge-success" style="font-size:10px;">Activo</span>
                            @endif
                        </td>
                        <td style="text-align:right;">
                            <div class="lp-row-actions">
                                <button wire:click="startEdit({{ $record->id }})" class="btn btn-ghost btn-sm" title="Editar">
                                    <i class="fa-solid fa-pen" style="color:var(--accent-light);"></i>
                                </button>
                                <button wire:click="deleteRecord({{ $record->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este registro DNS?')" title="Eliminar">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    <div wire:loading class="lp-loading-toast">
        <i class="fa-solid fa-spinner fa-spin"></i> Guardando en DNS...
    </div>
</div>
