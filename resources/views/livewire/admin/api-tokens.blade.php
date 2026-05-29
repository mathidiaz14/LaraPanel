<div style="display:grid;grid-template-columns:1fr;gap:24px;">
    @if(session()->has('message'))
        <div class="alert alert-success">
            <i class="fa-solid fa-check-circle"></i> {{ session('message') }}
        </div>
    @endif

    @if($plainTextToken)
        <div style="background:rgba(39,201,63,0.1);border:1px solid rgba(39,201,63,0.3);padding:24px;border-radius:8px;margin-bottom:24px;">
            <h3 style="font-size:16px;font-weight:700;color:#27c93f;margin-bottom:12px;">¡Token Creado Exitosamente!</h3>
            <p style="font-size:13px;margin-bottom:16px;">Por favor copia este token ahora. <strong>No volverá a mostrarse por razones de seguridad.</strong></p>
            <div style="display:flex;align-items:center;gap:12px;">
                <input type="text" readonly value="{{ $plainTextToken }}" class="form-input" style="font-family:monospace;font-size:14px;color:var(--text-primary);background:rgba(0,0,0,0.5);" onfocus="this.select()">
            </div>
        </div>
    @endif

    <div class="glass" style="padding:24px;">
        <h2 style="font-size:18px;font-weight:700;margin-bottom:20px;">Crear Nuevo Token API</h2>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:20px;">Utiliza estos tokens para dar acceso a la API REST de LaraPanel a sistemas externos como WHMCS, Blesta, o scripts personalizados.</p>
        
        <form wire:submit.prevent="createToken" style="display:flex;gap:16px;align-items:flex-end;">
            <div class="form-group" style="flex:1;">
                <label class="form-label">Nombre del Token (ej: WHMCS Integración)</label>
                <input type="text" wire:model="tokenName" class="form-input" required>
                @error('tokenName') <span style="color:var(--danger);font-size:11px;">{{ $message }}</span> @enderror
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary" style="height:38px;"><i class="fa-solid fa-key"></i> Generar Token</button>
            </div>
        </form>
    </div>

    <div class="glass" style="padding:24px;">
        <h2 style="font-size:18px;font-weight:700;margin-bottom:20px;">Tokens Activos</h2>
        
        <table class="table" style="width:100%;">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Último Uso</th>
                    <th>Creado El</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tokens as $token)
                <tr>
                    <td style="font-weight:600;">{{ $token->name }}</td>
                    <td style="font-size:12px;color:var(--text-muted);">
                        {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Nunca' }}
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);">{{ $token->created_at->format('Y-m-d H:i') }}</td>
                    <td>
                        <button wire:click="revokeToken({{ $token->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de revocar este token? Las integraciones que lo usen dejarán de funcionar inmediatamente.')">
                            <i class="fa-solid fa-trash"></i> Revocar
                        </button>
                    </td>
                </tr>
                @endforeach
                @if($tokens->isEmpty())
                <tr>
                    <td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted);">No tienes tokens activos.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
