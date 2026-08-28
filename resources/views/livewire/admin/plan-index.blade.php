<div>
    @if(session()->has('message'))
        <div class="alert alert-success" style="margin-bottom:var(--sp-6);">
            <i class="fa-solid fa-check-circle"></i> {{ session('message') }}
        </div>
    @endif

    @if(!$isEditing)
        <div class="glass" style="padding:var(--sp-6);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--sp-6);">
                <div>
                    <h2 style="font-size:var(--text-lg);font-weight:700;"><i class="fa-solid fa-box-open" style="color:var(--accent-light);margin-right:var(--sp-2);"></i> Planes de Hosting</h2>
                    <p style="font-size:var(--text-sm);color:var(--text-muted);">Gestiona los límites y permisos que se asignan a los clientes.</p>
                </div>
                <button wire:click="create" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Crear Plan</button>
            </div>
            <div class="table-responsive">
                <table class="lp-table">
                    <thead>
                        <tr>
                            <th>Nombre del Plan</th>
                            <th>Precio</th>
                            <th>Dominios</th>
                            <th>Disco (GB)</th>
                            <th>Clientes</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($plans as $plan)
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $plan->name }}</div>
                                <div style="font-size:var(--text-xs);color:var(--text-muted);">{{ Str::limit($plan->description, 30) }}</div>
                            </td>
                            <td>${{ number_format($plan->price, 2) }}</td>
                            <td>{{ $plan->max_domains == -1 ? 'Ilimitados' : $plan->max_domains }}</td>
                            <td>{{ $plan->diskQuotaGb() }} GB</td>
                            <td><span class="badge badge-muted">{{ $plan->users_count }}</span></td>
                            <td>
                                @if($plan->is_active)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-danger">Inactivo</span>
                                @endif
                            </td>
                            <td>
                                <button wire:click="edit({{ $plan->id }})" class="btn btn-ghost btn-sm"><i class="fa-solid fa-edit"></i> Editar</button>
                            </td>
                        </tr>
                        @endforeach
                        @if($plans->isEmpty())
                        <tr>
                            <td colspan="7" style="text-align:center;padding:var(--sp-6);color:var(--text-muted);">No hay planes creados.</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="glass" style="padding:var(--sp-8);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--sp-6);border-bottom:1px solid var(--glass-border);padding-bottom:var(--sp-4);">
                <h2 style="font-size:var(--text-lg);font-weight:700;">{{ $planId ? 'Editar Plan' : 'Crear Nuevo Plan' }}</h2>
                <button wire:click="resetForm" class="btn btn-ghost btn-sm"><i class="fa-solid fa-times"></i> Volver al Listado</button>
            </div>
            
            <form wire:submit.prevent="save">
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:var(--sp-6);margin-bottom:var(--sp-6);">
                    <div class="form-group">
                        <label class="form-label">Nombre del Plan</label>
                        <input type="text" wire:model="name" class="form-input" placeholder="Ej: Starter, Pro, Agency" required>
                        @error('name') <span style="color:var(--danger);font-size:var(--text-xs);">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Precio Mensual ($)</label>
                        <input type="number" step="0.01" wire:model="price" class="form-input">
                        @error('price') <span style="color:var(--danger);font-size:var(--text-xs);">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:var(--sp-6);">
                    <label class="form-label">Descripción</label>
                    <textarea wire:model="description" class="form-input" rows="2"></textarea>
                </div>

                <h3 style="font-size:var(--text-base);font-weight:600;margin-bottom:var(--sp-4);color:var(--accent-light);border-bottom:1px solid var(--glass-border);padding-bottom:var(--sp-2);">Límites de Recursos (-1 para Ilimitado)</h3>
                
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:var(--sp-4);margin-bottom:var(--sp-6);">
                    <div class="form-group">
                        <label class="form-label">Dominios Permitidos</label>
                        <input type="number" wire:model="max_domains" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subdominios</label>
                        <input type="number" wire:model="max_subdomains" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cuentas FTP</label>
                        <input type="number" wire:model="max_ftp_accounts" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bases de Datos</label>
                        <input type="number" wire:model="max_databases" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cuentas de Email</label>
                        <input type="number" wire:model="max_email_accounts" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cron Jobs</label>
                        <input type="number" wire:model="max_cron_jobs" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cuota Disco (Bytes)</label>
                        <input type="number" wire:model="disk_quota_bytes" class="form-input">
                        <div style="font-size:var(--text-xs);color:var(--text-muted);margin-top:var(--sp-1);">1GB = 1073741824</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ancho de Banda (Bytes)</label>
                        <input type="number" wire:model="bandwidth_bytes" class="form-input">
                    </div>
                </div>

                <h3 style="font-size:var(--text-base);font-weight:600;margin-bottom:var(--sp-4);color:var(--accent-light);border-bottom:1px solid var(--glass-border);padding-bottom:var(--sp-2);">Permisos Adicionales</h3>
                
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:var(--sp-4);margin-bottom:var(--sp-8);">
                    <label style="display:flex;align-items:center;gap:var(--sp-2);cursor:pointer;font-size:var(--text-sm);">
                        <input type="checkbox" wire:model="ssl_enabled"> Let's Encrypt SSL
                    </label>
                    <label style="display:flex;align-items:center;gap:var(--sp-2);cursor:pointer;font-size:var(--text-sm);">
                        <input type="checkbox" wire:model="backups_enabled"> Auto Backups
                    </label>
                    <label style="display:flex;align-items:center;gap:var(--sp-2);cursor:pointer;font-size:var(--text-sm);">
                        <input type="checkbox" wire:model="terminal_enabled"> Acceso Terminal Web
                    </label>
                    <label style="display:flex;align-items:center;gap:var(--sp-2);cursor:pointer;font-size:var(--text-sm);color:var(--success);">
                        <input type="checkbox" wire:model="is_active"> Plan Disponible para Venta
                    </label>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:var(--sp-3);">
                    <button type="button" wire:click="resetForm" class="btn btn-ghost">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar Plan</button>
                </div>
            </form>
        </div>
    @endif
</div>
