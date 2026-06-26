<div style="display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start;">
    {{-- Sidebar: Instalaciones y Dominios --}}
    <div class="glass lp-panel" style="padding:16px;">
        <h2 class="panel-title" style="margin-bottom:16px;"><i class="fa-brands fa-wordpress" style="color:#21759b;margin-right:8px;"></i> Dominios</h2>
        
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($domains as $domain)
            <button wire:click="selectDomain('{{ $domain['name'] }}')" 
                style="width:100%;text-align:left;padding:12px;border-radius:8px;border:1px solid {{ ($selectedDomain === $domain['name']) ? 'rgba(99,102,241,0.5)' : 'var(--glass-border)' }};background:{{ ($selectedDomain === $domain['name']) ? 'rgba(99,102,241,0.1)' : 'rgba(255,255,255,0.03)' }};cursor:pointer;transition:all 0.2s;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:4px;">{{ $domain['name'] }}</div>
                    @if($domain['has_wp'])
                        <div style="font-size:11px;color:#27c93f;display:flex;align-items:center;gap:4px;"><i class="fa-solid fa-check-circle"></i> Instalado</div>
                    @else
                        <div style="font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:4px;"><i class="fa-regular fa-circle"></i> Sin WP</div>
                    @endif
                </div>
                <i class="fa-solid fa-chevron-right" style="font-size:10px;opacity:0.5;"></i>
            </button>
            @endforeach
        </div>
    </div>

    {{-- Main Panel --}}
    <div>
        @if(!$selectedDomain)
            <div class="glass lp-panel" style="padding:60px 20px;text-align:center;">
                <i class="fa-brands fa-wordpress" style="font-size:48px;color:#21759b;opacity:0.2;margin-bottom:16px;display:block;"></i>
                <h3 class="panel-title" style="font-size:18px;margin-bottom:8px;">Instalador 1-Click</h3>
                <p style="color:var(--text-secondary);font-size:13px;max-width:400px;margin:0 auto;">
                    Selecciona un dominio en la barra lateral para instalar WordPress automáticamente o gestionar una instalación existente.
                </p>
            </div>
        @else
            @php $currentDomainInfo = collect($domains)->firstWhere('name', $selectedDomain); @endphp

            @if($currentDomainInfo && $currentDomainInfo['has_wp'])
                <div class="glass lp-panel" style="padding:24px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;border-bottom:1px solid var(--glass-border);padding-bottom:16px;">
                        <div>
                            <h2 class="panel-title" style="font-size:20px;margin-bottom:4px;display:flex;align-items:center;gap:12px;">
                                <i class="fa-brands fa-wordpress" style="color:#21759b;"></i> {{ $selectedDomain }}
                            </h2>
                            <p style="font-size:12px;color:var(--text-muted);">Gestionando instalación de WordPress</p>
                        </div>
                        <a href="https://{{ $selectedDomain }}/wp-admin" target="_blank" class="btn btn-primary btn-sm"><i class="fa-solid fa-external-link-alt"></i> WP Admin</a>
                    </div>
                    
                    <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:16px;margin-bottom:24px;">
                        <div style="background:rgba(255,255,255,0.02);padding:16px;border-radius:8px;border:1px solid var(--glass-border);">
                            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:1px;">Versión Core</div>
                            <div style="font-size:18px;font-weight:700;">6.5.2</div>
                        </div>
                        <div style="background:rgba(255,255,255,0.02);padding:16px;border-radius:8px;border:1px solid var(--glass-border);">
                            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:1px;">Estado DB</div>
                            <div style="font-size:14px;font-weight:600;color:#27c93f;">Conectada</div>
                        </div>
                        <div style="background:rgba(255,255,255,0.02);padding:16px;border-radius:8px;border:1px solid var(--glass-border);">
                            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:1px;">Auto-Updates</div>
                            <div style="font-size:14px;font-weight:600;color:var(--warning);">Solo Menores</div>
                        </div>
                    </div>

                    <div class="alert alert-info" style="font-size:12px;">
                        <i class="fa-solid fa-info-circle"></i> Las funciones avanzadas de gestión de plugins y temas se implementarán en la próxima versión.
                    </div>
                </div>
            @else
                <div class="glass lp-panel" style="padding:24px;">
                    <div style="margin-bottom:24px;border-bottom:1px solid var(--glass-border);padding-bottom:16px;">
                        <h2 class="panel-title" style="font-size:20px;margin-bottom:4px;display:flex;align-items:center;gap:12px;">
                            Instalar WordPress en <span style="color:var(--accent-light);">{{ $selectedDomain }}</span>
                        </h2>
                        <p style="font-size:12px;color:var(--text-muted);">El proceso configurará la Base de Datos, Nginx y descargará el core automáticamente.</p>
                    </div>

                    @if($installOutput)
                        <div class="alert {{ $installSuccess ? 'alert-success' : 'alert-danger' }}" style="margin-bottom:20px;">
                            <i class="fa-solid {{ $installSuccess ? 'fa-check-circle' : 'fa-times-circle' }}"></i> 
                            {{ $installSuccess ? 'Instalación exitosa' : 'Ocurrió un error' }}
                        </div>
                        
                        <pre style="background:rgba(0,0,0,0.5);border:1px solid var(--glass-border);border-radius:8px;padding:16px;font-family:monospace;font-size:11px;color:#cdd6f4;line-height:1.6;white-space:pre-wrap;margin-bottom:24px;">{{ $installOutput }}</pre>
                        
                        @if($installSuccess)
                        <div style="background:rgba(39,201,63,0.1);border:1px solid rgba(39,201,63,0.3);padding:16px;border-radius:8px;">
                            <h3 style="font-size:14px;font-weight:700;color:#27c93f;margin-bottom:12px;">Tus credenciales de administrador:</h3>
                            <div style="display:flex;gap:16px;margin-bottom:8px;">
                                <div style="width:80px;font-size:12px;color:var(--text-muted);">URL Admin:</div>
                                <div style="font-size:12px;font-weight:600;"><a href="https://{{ $selectedDomain }}/wp-admin" target="_blank" style="color:var(--accent-light);">https://{{ $selectedDomain }}/wp-admin</a></div>
                            </div>
                            <div style="display:flex;gap:16px;margin-bottom:8px;">
                                <div style="width:80px;font-size:12px;color:var(--text-muted);">Usuario:</div>
                                <div style="font-size:12px;font-weight:600;font-family:monospace;">{{ $adminUser }}</div>
                            </div>
                            <div style="display:flex;gap:16px;">
                                <div style="width:80px;font-size:12px;color:var(--text-muted);">Contraseña:</div>
                                <div style="font-size:12px;font-weight:600;font-family:monospace;">{{ $adminPass }}</div>
                            </div>
                        </div>
                        @endif
                    @else
                        <form wire:submit.prevent="installWP">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                                <div class="form-group">
                                    <label class="form-label">Título del Sitio</label>
                                    <input type="text" wire:model="siteTitle" class="form-input" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email del Administrador</label>
                                    <input type="email" wire:model="adminEmail" class="form-input" required>
                                </div>
                            </div>
                            
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:30px;">
                                <div class="form-group">
                                    <label class="form-label">Usuario Admin</label>
                                    <input type="text" wire:model="adminUser" class="form-input" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Contraseña Admin</label>
                                    <div style="position:relative;">
                                        <input type="text" wire:model="adminPass" class="form-input" style="font-family:monospace;" required>
                                        <button type="button" wire:click="$set('adminPass', '{{ Str::password(16, true, true, false, false) }}')" class="btn btn-ghost btn-sm" style="position:absolute;right:4px;top:4px;padding:4px 8px;">
                                            <i class="fa-solid fa-rotate"></i>
                                        </button>
                                    </div>
                                    <div style="font-size:11px;color:var(--text-muted);margin-top:6px;">Guarda esta contraseña, se utilizará para tu primer inicio de sesión.</div>
                                </div>
                            </div>

                            <div style="display:flex;justify-content:flex-end;">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                    <span wire:loading.remove><i class="fa-solid fa-download"></i> Instalar WordPress Ahora</span>
                                    <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Instalando (esto tomará un minuto)...</span>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @endif
        @endif
    </div>
</div>
