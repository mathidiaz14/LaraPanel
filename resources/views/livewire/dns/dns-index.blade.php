<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">DNS Manager</h1>
            <p class="page-subtitle">Gestione zonas DNS con PowerDNS. Administre registros A, AAAA, MX, TXT, CNAME, SRV y más.</p>
        </div>
        <button wire:click="$set('showCreate', true)" class="btn btn-primary">
            <i class="fa-solid fa-plus-circle"></i> Nueva Zona DNS
        </button>
    </div>

    {{-- Alerts --}}
    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    {{-- DNS Provider Notice --}}
    <div style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
        <i class="fa-solid fa-circle-info" style="color:var(--accent-light);font-size:18px;flex-shrink:0;"></i>
        <div style="font-size:12px;color:var(--text-secondary);">
            <strong style="color:var(--text-primary);">PowerDNS Authoritative Server</strong> gestiona estas zonas.
            Apunte los nameservers de sus dominios a: <code style="color:var(--accent-light);">ns1.tuservidor.com</code> y <code style="color:var(--accent-light);">ns2.tuservidor.com</code>.
        </div>
    </div>

    @if($zones->isEmpty())
    {{-- Empty state --}}
    <div class="glass lp-panel" style="text-align:center;padding:60px;">
        <div style="width:72px;height:72px;border-radius:20px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
            <i class="fa-solid fa-server" style="font-size:28px;color:var(--accent-light);"></i>
        </div>
        <h2 style="font-size:17px;font-weight:700;margin-bottom:8px;">Sin Zonas DNS Configuradas</h2>
        <p style="color:var(--text-secondary);font-size:13px;max-width:400px;margin:0 auto 20px;">Crea tu primera zona DNS y LaraPanel pre-poblará los registros base para tu dominio.</p>
        <button wire:click="$set('showCreate', true)" class="btn btn-primary">
            <i class="fa-solid fa-plus-circle"></i> Crear Primera Zona DNS
        </button>
    </div>
    @else
    {{-- Zones Table --}}
    <div class="glass" style="overflow:hidden;">
        <div class="table-responsive">
            <table class="lp-table">
                <thead>
                    <tr>
                        <th>Zona DNS</th>
                        <th>Nameservers</th>
                        <th>Registros</th>
                        <th>Estado</th>
                        <th style="text-align:right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($zones as $zone)
                    <tr wire:key="zone-{{ $zone->id }}">
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;border-radius:8px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.2);display:flex;align-items:center;justify-content:center;">
                                    <i class="fa-solid fa-globe" style="color:var(--accent-light);font-size:14px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight:600;font-size:14px;color:var(--text-primary);">{{ $zone->name }}</div>
                                    <div style="font-size:11px;color:var(--text-muted);">{{ $zone->type }} · Serial: {{ $zone->serial }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:12px;color:var(--text-secondary);line-height:1.4;">
                                <div><code style="color:var(--accent-light);">{{ $zone->primary_ns }}</code></div>
                                @if($zone->secondary_ns)
                                <div><code style="color:var(--accent-light);">{{ $zone->secondary_ns }}</code></div>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;flex-wrap:wrap;max-width:320px;">
                                @foreach($zone->recordsCountByType() as $type => $count)
                                <span style="background:rgba(255,255,255,0.06);border:1px solid var(--glass-border);border-radius:4px;padding:1px 6px;font-size:10px;font-family:monospace;color:var(--text-primary);">
                                    {{ $type }}:{{ $count }}
                                </span>
                                @endforeach
                                @if($zone->records->isEmpty())
                                <span style="font-size:11px;color:var(--text-muted);">Sin registros</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="badge {{ $zone->is_active ? 'badge-success' : 'badge-muted' }}">
                                {{ $zone->is_active ? 'Activa' : 'Inactiva' }}
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <div class="lp-row-actions" style="justify-content:flex-end;">
                                <a href="{{ route('dns.zone', $zone->id) }}" class="btn btn-ghost btn-sm" title="Editar Registros">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <button wire:click="applyEmailTemplate({{ $zone->id }})" class="btn btn-ghost btn-sm" title="Aplicar plantilla de email (MX, SPF, DMARC)" style="color:var(--success);">
                                    <i class="fa-solid fa-envelope-circle-check"></i>
                                </button>
                                <button wire:click="deleteZone({{ $zone->id }})" class="btn btn-danger btn-sm" title="Eliminar Zona" onclick="return confirm('¿Eliminar zona DNS {{ $zone->name }}? Todos los registros se perderán.')">
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
    @endif

    {{-- Create Zone Modal --}}
    @if($showCreate)
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated" style="max-width:480px;">
            <div class="lp-modal-header">
                <h3 class="panel-title" style="margin:0;">
                    <i class="fa-solid fa-globe" style="color:var(--accent-light);margin-right:8px;"></i>
                    Nueva Zona DNS
                </h3>
                <button wire:click="$set('showCreate', false)" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="lp-modal-body">
                <div class="form-group">
                    <label class="form-label">Seleccionar Dominio</label>
                    <select wire:model="domainId" class="form-input">
                        <option value="">Seleccione un dominio...</option>
                        @foreach($domainsWithoutZone as $domain)
                        <option value="{{ $domain->id }}">{{ $domain->name }}</option>
                        @endforeach
                    </select>
                    @error('domainId') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                @if($domainsWithoutZone->isEmpty())
                <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px;">
                    Todos sus dominios ya tienen zonas DNS configuradas.
                </div>
                @endif

                <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:8px;padding:12px;margin-bottom:20px;font-size:12px;color:var(--text-secondary);">
                    <i class="fa-solid fa-magic-wand-sparkles" style="color:var(--success);margin-right:6px;"></i>
                    LaraPanel creará automáticamente registros: <strong>A</strong> (www + @), <strong>MX</strong> apuntando a mail.dominio.com.
                </div>
            </div>

            <div class="lp-modal-footer">
                <button wire:click="$set('showCreate', false)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="createZone" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span wire:loading.remove><i class="fa-solid fa-rocket"></i> Crear Zona DNS</span>
                    <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Creando...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    <div wire:loading class="lp-loading-toast">
        <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
    </div>
</div>
