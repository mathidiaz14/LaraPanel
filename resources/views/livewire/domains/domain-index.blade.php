<div>
    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">Gestión de Dominios</h1>
            <p style="color:var(--text-secondary);font-size:13px;">
                {{ $domains->count() }} dominio(s) en este servidor
            </p>
        </div>
        <a href="{{ route('domains.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Nuevo Dominio
        </a>
    </div>

    @if($successMessage)
    <div class="alert alert-success" x-data x-init="setTimeout(() => $el.remove(), 4000)">
        <i class="fa-solid fa-circle-check"></i> {{ $successMessage }}
    </div>
    @endif

    {{-- Filters --}}
    <div style="display:flex;gap:10px;margin-bottom:20px;align-items:center;">
        <div style="position:relative;flex:1;max-width:360px;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
            <input wire:model.live.debounce.300ms="search"
                   type="text" placeholder="Buscar dominio..."
                   class="form-input" style="padding-left:36px;">
        </div>
        <div style="display:flex;gap:6px;">
            @foreach(['all' => 'Todos', 'active' => 'Activos', 'suspended' => 'Suspendidos'] as $val => $label)
            <button wire:click="$set('filter','{{ $val }}')"
                    class="btn {{ $filter === $val ? 'btn-primary' : 'btn-ghost' }} btn-sm">
                {{ $label }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- Domains Table --}}
    <div class="glass" style="overflow:hidden;">
        @if($domains->isEmpty())
            <div style="padding:60px;text-align:center;">
                <div style="font-size:40px;opacity:0.3;margin-bottom:16px;">
                    <i class="fa-solid fa-globe"></i>
                </div>
                <p style="color:var(--text-secondary);margin-bottom:18px;">No tienes dominios configurados aún.</p>
                <a href="{{ route('domains.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Crear primer dominio
                </a>
            </div>
        @else
        <table class="lp-table">
            <thead>
                <tr>
                    <th>Dominio</th>
                    <th>Tipo</th>
                    <th>PHP</th>
                    <th>SSL</th>
                    <th>Estado</th>
                    <th>Creado</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($domains as $domain)
                <tr wire:key="domain-{{ $domain->id }}">
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;border-radius:8px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.2);display:flex;align-items:center;justify-content:center;">
                                <i class="fa-solid fa-globe" style="color:var(--accent-light);font-size:14px;"></i>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:14px;">{{ $domain->name }}</div>
                                <div style="font-size:11px;color:var(--text-muted);">{{ $domain->document_root }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-muted" style="text-transform:capitalize;">
                            {{ $domain->type }}
                        </span>
                    </td>
                    <td>
                        <span style="font-family:monospace;font-size:12px;color:var(--text-secondary);">
                            PHP {{ $domain->php_version }}
                        </span>
                    </td>
                    <td>
                        @if($domain->ssl_enabled)
                            <span class="badge badge-success">
                                <i class="fa-solid fa-lock" style="font-size:9px;"></i>
                                SSL
                                @if($domain->sslIsExpiringSoon())
                                    <span style="color:var(--warning)"> · expira pronto</span>
                                @endif
                            </span>
                        @else
                            <span class="badge badge-muted">Sin SSL</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusMap = [
                                'active'      => ['badge-success', 'Activo'],
                                'pending'     => ['badge-warning', 'Pendiente'],
                                'suspended'   => ['badge-danger',  'Suspendido'],
                                'error'       => ['badge-danger',  'Error'],
                            ];
                            [$cls, $label] = $statusMap[$domain->status] ?? ['badge-muted', $domain->status];
                        @endphp
                        <span class="badge {{ $cls }}">{{ $label }}</span>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);">
                        {{ $domain->created_at->diffForHumans() }}
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex;gap:6px;justify-content:flex-end;">
                            {{-- Manage SSL --}}
                            <a href="{{ route('ssl.index') }}" class="btn btn-ghost btn-sm" title="SSL">
                                <i class="fa-solid fa-lock"></i>
                            </a>

                            {{-- Suspend / Unsuspend --}}
                            @if($domain->status === 'active')
                            <button wire:click="suspendDomain({{ $domain->id }})"
                                    wire:confirm="¿Suspender el dominio {{ $domain->name }}?"
                                    class="btn btn-ghost btn-sm" title="Suspender"
                                    style="color:var(--warning)">
                                <i class="fa-solid fa-pause"></i>
                            </button>
                            @elseif($domain->status === 'suspended')
                            <button wire:click="unsuspendDomain({{ $domain->id }})"
                                    class="btn btn-ghost btn-sm" title="Reactivar"
                                    style="color:var(--success)">
                                <i class="fa-solid fa-play"></i>
                            </button>
                            @endif

                            {{-- Delete --}}
                            <button wire:click="confirmDelete({{ $domain->id }})"
                                    class="btn btn-danger btn-sm" title="Eliminar">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Delete Confirmation Modal --}}
    @if($deletingId)
    @php $delDomain = $domains->firstWhere('id', $deletingId); @endphp
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="max-width:480px;width:100%;padding:32px;margin:16px;">
            <div style="text-align:center;margin-bottom:24px;">
                <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,0.15);border:2px solid rgba(239,68,68,0.3);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                    <i class="fa-solid fa-trash" style="color:var(--danger);font-size:22px;"></i>
                </div>
                <h2 style="font-size:18px;font-weight:700;margin-bottom:8px;">Eliminar dominio</h2>
                <p style="color:var(--text-secondary);font-size:14px;">
                    ¿Estás seguro de que quieres eliminar
                    <strong style="color:var(--text-primary)">{{ $delDomain?->name }}</strong>?
                </p>
            </div>

            <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);border-radius:8px;cursor:pointer;margin-bottom:20px;">
                <input type="checkbox" wire:model="deleteFiles" style="accent-color:var(--danger);">
                <div>
                    <div style="font-size:13px;font-weight:600;color:var(--danger);">Eliminar archivos del servidor</div>
                    <div style="font-size:11px;color:var(--text-muted);">Borrará permanentemente <code>{{ $delDomain?->document_root }}</code></div>
                </div>
            </label>

            <div style="display:flex;gap:10px;">
                <button wire:click="cancelDelete" class="btn btn-ghost" style="flex:1;justify-content:center;">
                    Cancelar
                </button>
                <button wire:click="deleteDomain" class="btn btn-danger" style="flex:1;justify-content:center;">
                    <i class="fa-solid fa-trash"></i>
                    Eliminar dominio
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Wire loading indicator --}}
    <div wire:loading.delay style="position:fixed;bottom:24px;right:24px;z-index:300;">
        <div class="glass" style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
        </div>
    </div>
</div>
