<div>
    @if(session()->has('message'))
        <div class="alert alert-success" style="margin-bottom:20px;">
            <i class="fa-solid fa-check-circle"></i> {{ session('message') }}
        </div>
    @endif

    @if(!$isEditing)
        <div class="glass" style="padding:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
                <div>
                    <h2 style="font-size:18px;font-weight:700;"><i class="fa-solid fa-box-open" style="color:var(--accent-light);margin-right:8px;"></i> Planes de Hosting</h2>
                    <p style="font-size:12px;color:var(--text-muted);">Gestiona los límites y permisos que se asignan a los clientes.</p>
                </div>
                <button wire:click="create" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Crear Plan</button>
            </div>

            <table class="table" style="width:100%;">
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
                            <div style="font-size:11px;color:var(--text-muted);">{{ Str::limit($plan->description, 30) }}</div>
                        </td>
                        <td>${{ number_format($plan->price, 2) }}</td>
                        <td>{{ $plan->max_domains == -1 ? 'Ilimitados' : $plan->max_domains }}</td>
                        <td>{{ $plan->diskQuotaGb() }} GB</td>
                        <td><span class="badge badge-info">{{ $plan->users_count }}</span></td>
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
                        <td colspan="7" style="text-align:center;padding:20px;color:var(--text-muted);">No hay planes creados.</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    @else
        <div class="glass" style="padding:24px;">
            <h2 style="font-size:18px;font-weight:700;margin-bottom:24px;">{{ $planId ? 'Editar Plan' : 'Crear Nuevo Plan' }}</h2>
            
            <form wire:submit.prevent="save">
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;">
                    <div class="form-group">
                        <label class="form-label">Nombre del Plan</label>
                        <input type="text" wire:model="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Precio Mensual ($)</label>
                        <input type="number" step="0.01" wire:model="price" class="form-input">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:24px;">
                    <label class="form-label">Descripción</label>
                    <textarea wire:model="description" class="form-input" rows="2"></textarea>
                </div>

                <h3 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--accent-light);border-bottom:1px solid var(--glass-border);padding-bottom:8px;">Límites de Recursos (-1 para Ilimitado)</h3>
                
                <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:16px;margin-bottom:20px;">
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
                        <div style="font-size:10px;color:var(--text-muted);margin-top:4px;">1GB = 1073741824</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ancho de Banda (Bytes)</label>
                        <input type="number" wire:model="bandwidth_bytes" class="form-input">
                    </div>
                </div>

                <h3 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--accent-light);border-bottom:1px solid var(--glass-border);padding-bottom:8px;">Permisos Adicionales</h3>
                
                <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:16px;margin-bottom:30px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                        <input type="checkbox" wire:model="ssl_enabled"> Let's Encrypt SSL
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                        <input type="checkbox" wire:model="backups_enabled"> Auto Backups
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                        <input type="checkbox" wire:model="terminal_enabled"> Acceso Terminal Web
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--success);">
                        <input type="checkbox" wire:model="is_active"> Plan Disponible para Venta
                    </label>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:12px;">
                    <button type="button" wire:click="resetForm" class="btn btn-ghost">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar Plan</button>
                </div>
            </form>
        </div>
    @endif
</div>
