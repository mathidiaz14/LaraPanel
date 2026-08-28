<div>
    <div class="page-header">
        <div style="flex:1">
            <h2 style="font-size:var(--text-2xl);font-weight:600;margin:0">Mi Perfil</h2>
            <p style="color:var(--text-muted);font-size:var(--text-base);margin:5px 0 0;">Gestiona tu información personal y seguridad.</p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(0,1fr);gap:var(--sp-8);max-width:1000px">
        
        <!-- Información Básica -->
        <div class="glass-elevated" style="padding:var(--sp-6);border-radius:var(--radius);">
            <h3 style="font-size:var(--text-lg);font-weight:600;margin:0 0 var(--sp-4);">Información del Perfil</h3>
            <p style="color:var(--text-muted);font-size:var(--text-base);margin-bottom:var(--sp-6);">Actualiza la información de tu cuenta y dirección de correo electrónico.</p>
            
            <form wire:submit="updateProfileInformation" style="max-width:500px">
                <div class="form-group">
                    <label class="form-label" for="name">Nombre</label>
                    <input id="name" type="text" class="form-input" wire:model="name">
                    @error('name') <span style="color:var(--danger);font-size:var(--text-sm);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" type="email" class="form-input" wire:model="email">
                    @error('email') <span style="color:var(--danger);font-size:var(--text-sm);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>

        <!-- Contraseña -->
        <div class="glass-elevated" style="padding:var(--sp-6);border-radius:var(--radius);">
            <h3 style="font-size:var(--text-lg);font-weight:600;margin:0 0 var(--sp-4);">Actualizar Contraseña</h3>
            <p style="color:var(--text-muted);font-size:var(--text-base);margin-bottom:var(--sp-6);">Asegúrate de usar una contraseña larga y aleatoria para mantener tu cuenta segura.</p>
            
            <form wire:submit="updatePassword" style="max-width:500px">
                <div class="form-group">
                    <label class="form-label" for="current_password">Contraseña Actual</label>
                    <input id="current_password" type="password" class="form-input" wire:model="current_password">
                    @error('current_password') <span style="color:var(--danger);font-size:var(--text-sm);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Nueva Contraseña</label>
                    <input id="password" type="password" class="form-input" wire:model="password">
                    @error('password') <span style="color:var(--danger);font-size:var(--text-sm);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
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
        <div class="glass-elevated" style="padding:var(--sp-6);border-radius:var(--radius);margin-bottom:var(--sp-12);">
            <h3 style="font-size:var(--text-lg);font-weight:600;margin:0 0 var(--sp-4);">Autenticación de Dos Factores (2FA)</h3>
            <p style="color:var(--text-muted);font-size:var(--text-base);margin-bottom:var(--sp-6);">Añade seguridad adicional a tu cuenta requiriendo un código TOTP cada vez que inicies sesión.</p>
            
            @if(! $this->user->two_factor_secret)
                <div style="background:rgba(255,255,255,0.02);border:1px solid var(--glass-border);padding:var(--sp-6);border-radius:var(--radius-sm);margin-bottom:var(--sp-6);">
                    <p style="margin:0 0 var(--sp-4);">No has habilitado la autenticación de dos factores.</p>
                    <button wire:click="enableTwoFactorAuthentication" class="btn btn-primary">
                        <i class="fa-solid fa-shield-halved"></i> Habilitar 2FA
                    </button>
                </div>
            @else
                @if($showingQrCode)
                    <div style="background:rgba(255,255,255,0.02);border:1px solid var(--glass-border);padding:var(--sp-6);border-radius:var(--radius-sm);margin-bottom:var(--sp-6);">
                        <p style="margin:0 0 var(--sp-4);font-weight:600;">Para terminar de habilitar la autenticación de dos factores, escanea el siguiente código QR usando tu aplicación de autenticación (Google Authenticator, Authy, etc).</p>
                        
                        <div style="background:#fff;padding:var(--sp-3);border-radius:var(--radius-sm);display:inline-block;margin-bottom:var(--sp-6);">
                            {!! $this->user->twoFactorQrCodeSvg() !!}
                        </div>

                        @if($showingConfirmation)
                            <div style="max-width:300px;margin-bottom:var(--sp-4);">
                                <label class="form-label" for="code">Código de Configuración</label>
                                <input id="code" type="text" class="form-input" wire:model="code" placeholder="123456" autofocus>
                                @error('code') <span style="color:var(--danger);font-size:var(--text-sm);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                            </div>
                            <button wire:click="confirmTwoFactorAuthentication" class="btn btn-primary">
                                <i class="fa-solid fa-check"></i> Confirmar
                            </button>
                        @endif
                    </div>
                @endif

                @if($showingRecoveryCodes)
                    <div style="background:rgba(255,255,255,0.02);border:1px solid var(--glass-border);padding:var(--sp-6);border-radius:var(--radius-sm);margin-bottom:var(--sp-6);">
                        <p style="margin:0 0 var(--sp-4);">Guarda estos códigos de recuperación en un lugar seguro. Pueden usarse para recuperar el acceso a tu cuenta si pierdes tu dispositivo de autenticación.</p>
                        
                        <div style="background:rgba(0,0,0,0.3);padding:var(--sp-3);border-radius:var(--radius-sm);font-family:monospace;font-size:var(--text-base);color:var(--accent-light);">
                            @foreach((array) $this->user->recoveryCodes() as $code)
                                <div style="margin-bottom:var(--sp-1);">{{ $code }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(! $showingQrCode)
                    <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);padding:var(--sp-3);border-radius:var(--radius-sm);margin-bottom:var(--sp-6);color:var(--success);">
                        <i class="fa-solid fa-circle-check" style="margin-right:var(--sp-2);"></i>
                        <strong>Has habilitado la autenticación de dos factores.</strong>
                    </div>
                @endif

                <div style="display:flex;gap:var(--sp-2);flex-wrap:wrap;">
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
