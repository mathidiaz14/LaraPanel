<div>
<div style="max-width:820px;margin:0 auto;">

    <div class="page-header" style="justify-content: flex-start; gap: 14px;">
        <a href="{{ route('domains.index') }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-network-wired" style="color:var(--info);"></i> Staging / Balanceo
            </h1>
            <p class="page-subtitle">
                Configura un grupo de nodos backend y decide si {{ $domain->name }} sirve tráfico en cluster.
            </p>
        </div>
    </div>

    @if($successMessage)
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    {{-- Status card --}}
    <div class="glass lp-panel" style="margin-bottom:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
            <div>
                <div style="font-size:13px;font-weight:700;margin-bottom:4px;">
                    @if($onStaging)
                    <span style="color:var(--success);"><i class="fa-solid fa-circle"></i> Sirviendo vía cluster de staging</span>
                    @else
                    <span style="color:var(--text-secondary);"><i class="fa-solid fa-circle"></i> Sirviendo desde backend local (PHP-FPM)</span>
                    @endif
                </div>
                <div style="font-size:11px;color:var(--text-muted);">
                    Al activar el cluster, el Nginx de este dominio proxy a los nodos del grupo en lugar de PHP-FPM local.
                    Se requiere al menos un nodo.
                </div>
            </div>
            <div>
                @if($onStaging)
                <button wire:click="toggleStaging" wire:loading.attr="disabled" class="btn btn-danger btn-sm">
                    <i class="fa-solid fa-rotate-left"></i> Volver a local
                </button>
                @else
                <button wire:click="toggleStaging" wire:loading.attr="disabled" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plug-circle-bolt"></i> Activar cluster
                </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Members --}}
    <div class="glass lp-panel" style="margin-bottom:20px;">
        <div class="form-label" style="margin-bottom:12px;">Nodos del grupo (upstream staging)</div>

        @if($members === [])
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">
            No hay nodos configurados. Añade el primero con el formulario inferior.
        </div>
        @else
        <div class="table-responsive">
        <table class="lp-table" style="margin-bottom:14px;">
            <thead>
                <tr>
                    <th>Dirección</th>
                    <th>Peso</th>
                    <th>Estado</th>
                    <th style="width:60px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($members as $member)
                <tr>
                    <td style="font-family:monospace;font-size:12px;">{{ $member['address'] }}</td>
                    <td>{{ $member['weight'] }}</td>
                    <td>
                        @if($member['healthy'])
                        <span class="badge badge-success">Disponible</span>
                        @else
                        <span class="badge badge-danger">Sin respuesta</span>
                        @endif
                    </td>
                    <td>
                        <button wire:click="removeMember('{{ $member['address'] }}')" class="btn btn-ghost btn-sm" title="Eliminar nodo">
                            <i class="fa-solid fa-trash" style="color:var(--danger);"></i>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @endif

        <form wire:submit="addMember" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input wire:model="newAddress" type="text" id="member-address" class="form-input" style="max-width:280px;"
                   placeholder="10.0.0.11:8080" pattern="[a-zA-Z0-9\-\.:]+:\d+" required>
            <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                <i class="fa-solid fa-plus"></i> Añadir nodo
            </button>
            <div style="font-size:11px;color:var(--text-muted);margin-left:2px;">formato: host:puerto</div>
        </form>
    </div>

    <div style="margin-top:14px;text-align:center;font-size:11px;color:var(--text-muted);">
        <i class="fa-solid fa-shield-halved" style="color:var(--info);margin-right:4px;"></i>
        La configuración Nginx se regenera y recarga automáticamente al cambiar el estado del cluster.
    </div>
</div>
</div>