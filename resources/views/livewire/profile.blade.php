<div>
    <div class="page-header">
        <div style="flex:1">
            <h2 style="font-size:24px;font-weight:600;margin:0">Mi Perfil</h2>
            <p style="color:var(--text-muted);font-size:14px;margin:5px 0 0;">Gestiona tu información personal y seguridad.</p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(0,1fr);gap:30px;max-width:1000px">
        
        <!-- Información Básica -->
        <div class="glass-elevated" style="padding:25px;border-radius:12px;">
            <h3 style="font-size:18px;font-weight:600;margin:0 0 15px;">Información del Perfil</h3>
            <p style="color:var(--text-muted);font-size:14px;margin-bottom:20px;">Actualiza la información de tu cuenta y dirección de correo electrónico.</p>
            
            <form wire:submit="updateProfileInformation" style="max-width:500px">
                <div class="form-group">
                    <label class="form-label" for="name">Nombre</label>
                    <input id="name" type="text" class="form-input" wire:model="name">
                    @error('name') <span style="color:var(--danger);font-size:13px;margin-top:5px;display:block;">{{ $message }}</span> @enderror
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" type="email" class="form-input" wire:model="email">
                    @error('email') <span style="color:var(--danger);font-size:13px;margin-top:5px;display:block;">{{ $message }}</span> @enderror
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>

        <!-- Contraseña -->
        <div class="glass-elevated" style="padding:25px;border-radius:12px;">
            <h3 style="font-size:18px;font-weight:600;margin:0 0 15px;">Actualizar Contraseña</h3>
            <p style="color:var(--text-muted);font-size:14px;margin-bottom:20px;">Asegúrate de usar una contraseña larga y aleatoria para mantener tu cuenta segura.</p>
            
            <form wire:submit="updatePassword" style="max-width:500px">
                <div class="form-group">
                    <label class="form-label" for="current_password">Contraseña Actual</label>
                    <input id="current_password" type="password" class="form-input" wire:model="current_password">
                    @error('current_password') <span style="color:var(--danger);font-size:13px;margin-top:5px;display:block;">{{ $message }}</span> @enderror
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Nueva Contraseña</label>
                    <input id="password" type="password" class="form-input" wire:model="password">
                    @error('password') <span style="color:var(--danger);font-size:13px;margin-top:5px;display:block;">{{ $message }}</span> @enderror
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password_confirmation">Confirmar Contraseña</label>
                    <input id="password_confirmation" type="password" class="form-input" wire:model="password_confirmation">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-key"></i> Actualizar Contraseña
                </button>
            </form>
        </div>

        <!-- 2FA -->
        <div class="glass-elevated" style="padding:25px;border-radius:12px;margin-bottom:40px;">
            <h3 style="font-size:18px;font-weight:600;margin:0 0 15px;">Autenticación de Dos Factores (2FA)</h3>
            <p style="color:var(--text-muted);font-size:14px;margin-bottom:20px;">Añade seguridad adicional a tu cuenta requiriendo un código TOTP cada vez que inicies sesión.</p>
            
            @if(! $this->user->two_factor_secret)
                <div style="background:rgba(255,255,255,0.02);border:1px solid var(--glass-border);padding:20px;border-radius:8px;margin-bottom:20px;">
                    <p style="margin:0 0 15px;">No has habilitado la autenticación de dos factores.</p>
                    <button wire:click="enableTwoFactorAuthentication" class="btn btn-primary">
                        <i class="fa-solid fa-shield-halved"></i> Habilitar 2FA
                    </button>
                </div>
            @else
                @if($showingQrCode)
                    <div style="background:rgba(255,255,255,0.02);border:1px solid var(--glass-border);padding:20px;border-radius:8px;margin-bottom:20px;">
                        <p style="margin:0 0 15px;font-weight:600;">Para terminar de habilitar la autenticación de dos factores, escanea el siguiente código QR usando tu aplicación de autenticación (Google Authenticator, Authy, etc).</p>
                        
                        <div style="background:#fff;padding:15px;border-radius:8px;display:inline-block;margin-bottom:20px;">
                            {!! $this->user->twoFactorQrCodeSvg() !!}
                        </div>

                        @if($showingConfirmation)
                            <div style="max-width:300px;margin-bottom:15px;">
                                <label class="form-label" for="code">Código de Configuración</label>
                                <input id="code" type="text" class="form-input" wire:model="code" placeholder="123456" autofocus>
                                @error('code') <span style="color:var(--danger);font-size:13px;margin-top:5px;display:block;">{{ $message }}</span> @enderror
                            </div>
                            <button wire:click="confirmTwoFactorAuthentication" class="btn btn-primary">
                                <i class="fa-solid fa-check"></i> Confirmar
                            </button>
                        @endif
                    </div>
                @endif

                @if($showingRecoveryCodes)
                    <div style="background:rgba(255,255,255,0.02);border:1px solid var(--glass-border);padding:20px;border-radius:8px;margin-bottom:20px;">
                        <p style="margin:0 0 15px;">Guarda estos códigos de recuperación en un lugar seguro. Pueden usarse para recuperar el acceso a tu cuenta si pierdes tu dispositivo de autenticación.</p>
                        
                        <div style="background:rgba(0,0,0,0.3);padding:15px;border-radius:8px;font-family:monospace;font-size:14px;color:var(--accent-light);">
                            @foreach((array) $this->user->recoveryCodes() as $code)
                                <div style="margin-bottom:5px;">{{ $code }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(! $showingQrCode)
                    <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);padding:15px;border-radius:8px;margin-bottom:20px;color:var(--success);">
                        <i class="fa-solid fa-circle-check" style="margin-right:8px;"></i>
                        <strong>Has habilitado la autenticación de dos factores.</strong>
                    </div>
                @endif

                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    @if($showingRecoveryCodes)
                        <button wire:click="regenerateRecoveryCodes" class="btn btn-ghost">
                            <i class="fa-solid fa-rotate"></i> Regenerar Códigos
                        </button>
                    @elseif(! $showingConfirmation)
                        <button wire:click="showRecoveryCodes" class="btn btn-ghost">
                            <i class="fa-solid fa-eye"></i> Mostrar Códigos
                        </button>
                    @endif

                    <button wire:click="disableTwoFactorAuthentication" class="btn btn-danger">
                        <i class="fa-solid fa-shield-xmark"></i> Deshabilitar 2FA
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
