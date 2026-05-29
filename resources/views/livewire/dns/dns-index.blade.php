<div>
    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">DNS Manager</h1>
            <p style="color:var(--text-secondary);font-size:13px;">Gestione zonas DNS con PowerDNS. Administre registros A, AAAA, MX, TXT, CNAME, SRV y más.</p>
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
    <div class="glass" style="padding:60px;text-align:center;">
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
    {{-- Zones Grid --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:16px;">
        @foreach($zones as $zone)
        <div class="glass" style="padding:20px;border-color:{{ $zone->is_active ? 'rgba(99,102,241,0.2)' : 'var(--glass-border)' }};">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:38px;height:38px;border-radius:10px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-globe" style="color:var(--accent-light);"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:14px;color:var(--text-primary);">{{ $zone->name }}</div>
                        <div style="font-size:11px;color:var(--text-muted);">{{ $zone->type }} · Serial: {{ $zone->serial }}</div>
                    </div>
                </div>
                <span class="badge {{ $zone->is_active ? 'badge-success' : 'badge-muted' }}" style="font-size:11px;">
                    {{ $zone->is_active ? 'Activa' : 'Inactiva' }}
                </span>
            </div>

            {{-- Records summary --}}
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;">
                @foreach($zone->recordsCountByType() as $type => $count)
                <span style="background:rgba(255,255,255,0.06);border:1px solid var(--glass-border);border-radius:6px;padding:2px 8px;font-size:11px;font-family:monospace;">
                    {{ $type }}: {{ $count }}
                </span>
                @endforeach
                @if($zone->records->isEmpty())
                <span style="font-size:11px;color:var(--text-muted);">Sin registros</span>
                @endif
            </div>

            {{-- NS info --}}
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:14px;line-height:1.7;">
                <i class="fa-solid fa-server" style="margin-right:4px;"></i>
                NS: <code>{{ $zone->primary_ns }}</code>
                @if($zone->secondary_ns)
                · <code>{{ $zone->secondary_ns }}</code>
                @endif
            </div>

            {{-- Actions --}}
            <div style="display:flex;gap:8px;border-top:1px solid var(--glass-border);padding-top:12px;">
                <a href="{{ route('dns.zone', $zone->id) }}" class="btn btn-ghost btn-sm" style="flex:1;justify-content:center;">
                    <i class="fa-solid fa-pen-to-square"></i> Editar Registros
                </a>
                <button wire:click="applyEmailTemplate({{ $zone->id }})" class="btn btn-ghost btn-sm" title="Agregar registros MX, SPF, DMARC" style="color:var(--success);">
                    <i class="fa-solid fa-envelope-circle-check"></i>
                </button>
                <button wire:click="deleteZone({{ $zone->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar zona DNS {{ $zone->name }}? Todos los registros se perderán.')">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Create Zone Modal --}}
    @if($showCreate)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="max-width:480px;width:100%;padding:28px;margin:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;">
                <h3 style="font-size:16px;font-weight:700;margin:0;">
                    <i class="fa-solid fa-globe" style="color:var(--accent-light);margin-right:8px;"></i>
                    Nueva Zona DNS
                </h3>
                <button wire:click="$set('showCreate', false)" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="form-group">
                <label class="form-label">Seleccionar Dominio</label>
                <select wire:model="domainId" class="form-input">
                    <option value="">Seleccione un dominio...</option>
                    @foreach($domainsWithoutZone as $domain)
                    <option value="{{ $domain->id }}">{{ $domain->name }}</option>
                    @endforeach
                </select>
                @error('domainId') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
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

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button wire:click="$set('showCreate', false)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="createZone" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span wire:loading.remove><i class="fa-solid fa-rocket"></i> Crear Zona DNS</span>
                    <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Creando...</span>
                </button>
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
