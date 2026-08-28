<div>
    @if(session()->has('message'))
        <div class="alert alert-success" style="margin-bottom:var(--sp-6);">
            <i class="fa-solid fa-check-circle"></i> {{ session('message') }}
        </div>
    @endif
    @if(session()->has('error'))
        <div class="alert alert-danger" style="margin-bottom:var(--sp-6);">
            <i class="fa-solid fa-triangle-exclamation"></i> {{ session('error') }}
        </div>
    @endif

    @if(!$isEditing)
        <div class="glass" style="padding:var(--sp-6);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--sp-6);">
                <div>
                    <h2 style="font-size:var(--text-lg);font-weight:700;"><i class="fa-solid fa-users" style="color:var(--accent-light);margin-right:var(--sp-2);"></i> Cuentas de Usuario</h2>
                    <p style="font-size:var(--text-sm);color:var(--text-muted);">Administra clientes, revendedores y administradores.</p>
                </div>
                <button wire:click="create" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Nuevo Usuario</button>
            </div>

            <div class="table-responsive">
                <table class="lp-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Plan Asignado</th>
                            <th>Dominios</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $user->name }}</div>
                                <div style="font-size:var(--text-xs);color:var(--text-muted);">{{ $user->email }}</div>
                            </td>
                            <td>
                                @if($user->role === 'admin')
                                    <span class="badge badge-danger">Admin</span>
                                @elseif($user->role === 'reseller')
                                    <span class="badge badge-warning">Reseller</span>
                                @else
                                    <span class="badge badge-accent">Cliente</span>
                                @endif
                            </td>
                            <td>
                                @if($user->plan)
                                    <span style="font-size:var(--text-sm);">{{ $user->plan->name }}</span>
                                @else
                                    <span style="font-size:var(--text-sm);color:var(--text-muted);">Ninguno</span>
                                @endif
                            </td>
                            <td><span class="badge badge-muted">{{ $user->domains_count }}</span></td>
                            <td>
                                @if($user->is_active)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-danger">Suspendido</span>
                                @endif
                            </td>
                            <td>
                                @if($user->id !== auth()->id())
                                    <a href="{{ route('admin.impersonate.start', $user->id) }}" class="btn btn-ghost btn-sm" title="Iniciar sesión como {{ $user->name }}" style="color:var(--accent-light); margin-right: var(--sp-1);">
                                        <i class="fa-solid fa-user-secret"></i>
                                    </a>
                                @endif
                                <button wire:click="edit({{ $user->id }})" class="btn btn-ghost btn-sm" title="Editar"><i class="fa-solid fa-edit"></i></button>
                                @if($user->is_active)
                                    <button wire:click="suspend({{ $user->id }})" class="btn btn-ghost btn-sm" title="Suspender Cuenta" style="color:var(--danger);" onclick="return confirm('¿Suspender usuario? Se desactivarán sus dominios.')"><i class="fa-solid fa-ban"></i></button>
                                @else
                                    <button wire:click="activate({{ $user->id }})" class="btn btn-ghost btn-sm" title="Reactivar Cuenta" style="color:var(--success);"><i class="fa-solid fa-check"></i></button>
                                @endif
                                <button wire:click="forceLogoutSessions({{ $user->id }})" class="btn btn-ghost btn-sm" title="Desconectar sesiones" style="color:var(--warning);" onclick="return confirm('¿Desconectar todas las sesiones activas de este usuario?')"><i class="fa-solid fa-right-from-bracket"></i></button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="glass" style="padding:var(--sp-6);">
            <h2 style="font-size:var(--text-lg);font-weight:700;margin-bottom:var(--sp-6);">{{ $userId ? 'Editar Usuario' : 'Crear Usuario' }}</h2>
            
            <form wire:submit.prevent="save">
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:var(--sp-6);margin-bottom:var(--sp-6);">
                    <div class="form-group">
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" wire:model="name" class="form-input" required>
                        @error('name') <span style="color:var(--danger);font-size:var(--text-xs);">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Correo Electrónico</label>
                        <input type="email" wire:model="email" class="form-input" required>
                        @error('email') <span style="color:var(--danger);font-size:var(--text-xs);">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-6);margin-bottom:var(--sp-6);">
                    <div class="form-group">
                        <label class="form-label">Contraseña</label>
                        <input type="password" wire:model="password" class="form-input" placeholder="{{ $userId ? 'Dejar en blanco para no cambiar' : 'Requerida' }}">
                        @error('password') <span style="color:var(--danger);font-size:var(--text-xs);">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rol del Sistema</label>
                        @if(auth()->user()->isAdmin())
                            <select wire:model="role" class="form-input">
                                <option value="client">Cliente Normal</option>
                                <option value="reseller">Reseller (Puede crear sub-clientes)</option>
                                <option value="admin">Administrador Total</option>
                            </select>
                        @else
                            <input type="text" class="form-input" value="Cliente Normal" readonly disabled>
                        @endif
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-6);margin-bottom:var(--sp-8);">
                    <div class="form-group">
                        <label class="form-label">Plan de Hosting Asignado</label>
                        <select wire:model="plan_id" class="form-input">
                            <option value="">Sin Plan (Modo Admin/Prueba)</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }} (${{ $plan->price }}/m)</option>
                            @endforeach
                        </select>
                        <div style="font-size:var(--text-xs);color:var(--text-muted);margin-top:var(--sp-1);">Define los límites de dominios y disco.</div>
                    </div>
                    <div class="form-group" style="display:flex;flex-direction:column;justify-content:center;">
                        <label style="display:flex;align-items:center;gap:var(--sp-2);cursor:pointer;font-size:var(--text-sm);padding-top:var(--sp-4);">
                            <input type="checkbox" wire:model="is_active"> 
                            <span class="badge {{ $is_active ? 'badge-success' : 'badge-danger' }}">
                                {{ $is_active ? 'Cuenta Activa' : 'Cuenta Suspendida' }}
                            </span>
                        </label>
                        @if(!$is_active && $userId)
                            <div style="font-size:var(--text-xs);color:var(--danger);margin-top:var(--sp-1);">Nota: Al suspender, se apagarán los vhosts de Nginx.</div>
                        @endif
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:var(--sp-3);">
                    <button type="button" wire:click="resetForm" class="btn btn-ghost">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar Usuario</button>
                </div>
            </form>
        </div>
    @endif
</div>
