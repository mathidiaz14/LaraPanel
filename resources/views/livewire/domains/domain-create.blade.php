<div>
    <div style="max-width:680px;margin:0 auto;">
        {{-- Header --}}
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:28px;">
            <a href="{{ route('domains.index') }}" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 style="font-size:20px;font-weight:700;margin-bottom:2px;">Agregar Dominio</h1>
                <p style="font-size:13px;color:var(--text-secondary);">Configura un nuevo dominio o subdominio en el servidor</p>
            </div>
        </div>

        @if($errorMessage)
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}
        </div>
        @endif

        <div class="glass" style="padding:28px;">
            {{-- Domain Type --}}
            <div class="form-group">
                <label class="form-label">Tipo de dominio</label>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">
                    @foreach([
                        'main'      => ['fa-globe',   'Principal', 'Dominio raíz'],
                        'subdomain' => ['fa-sitemap', 'Subdominio', 'sub.dominio.com'],
                        'addon'     => ['fa-plus-circle', 'Addon', 'Dominio extra'],
                        'parked'    => ['fa-parking', 'Parked', 'Alias de otro'],
                    ] as $val => [$icon, $label, $desc])
                    <label style="cursor:pointer;">
                        <input type="radio" wire:model.live="type" value="{{ $val }}" style="display:none;">
                        <div style="padding:12px 8px;border-radius:8px;text-align:center;border:1px solid {{ $type === $val ? 'rgba(99,102,241,0.5)' : 'var(--glass-border)' }};background:{{ $type === $val ? 'rgba(99,102,241,0.12)' : 'var(--glass-bg)' }};transition:all 0.2s;">
                            <i class="fa-solid {{ $icon }}" style="font-size:18px;color:{{ $type === $val ? 'var(--accent-light)' : 'var(--text-muted)' }};margin-bottom:6px;display:block;"></i>
                            <div style="font-size:12px;font-weight:600;color:{{ $type === $val ? 'var(--text-primary)' : 'var(--text-secondary)' }};">{{ $label }}</div>
                            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">{{ $desc }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Domain Name --}}
            <div class="form-group">
                <label class="form-label" for="domain-name">
                    {{ $type === 'subdomain' ? 'Nombre del subdominio' : 'Nombre del dominio' }}
                    <span style="color:var(--danger)">*</span>
                </label>
                <input id="domain-name"
                       wire:model.live="name"
                       type="text"
                       class="form-input"
                       placeholder="{{ $type === 'subdomain' ? 'blog.tudominio.com' : 'tudominio.com' }}"
                       autocomplete="off">
                @error('name') <div style="font-size:12px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
            </div>

            {{-- Parent domain (for subdomains) --}}
            @if($type === 'subdomain' || $type === 'parked')
            <div class="form-group">
                <label class="form-label" for="parent-domain">
                    {{ $type === 'subdomain' ? 'Dominio padre (el subdominio cuelga de este)' : 'Dominio raíz al que apuntar (Parked)' }}
                </label>
                <select id="parent-domain" wire:model.live="parent_domain" class="form-input">
                    <option value="">Seleccione dominio existente...</option>
                    @foreach($existingDomains as $existingDomain)
                    <option value="{{ $existingDomain->name }}">{{ $existingDomain->name }}</option>
                    @endforeach
                </select>
                @if($parent_domain && $type === 'subdomain')
                <div style="font-size:11px;color:var(--accent-light);margin-top:5px;">
                    <i class="fa-solid fa-arrow-right"></i>
                    El subdominio completo será: <strong>{{ $name ? $name . '.' . $parent_domain : '[nombre].' . $parent_domain }}</strong>
                </div>
                @endif
                @error('parent_domain') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
            </div>
            @endif

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                {{-- PHP Version --}}
                <div class="form-group">
                    <label class="form-label">Versión de PHP</label>
                    <select wire:model="php_version" class="form-input">
                        @foreach($phpVersions as $version)
                        <option value="{{ $version }}">PHP {{ $version }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Web Server --}}
                <div class="form-group">
                    <label class="form-label">Servidor web</label>
                    <select wire:model="webserver" class="form-input">
                        <option value="nginx">Nginx (recomendado)</option>
                        <option value="apache">Apache</option>
                        <option value="both">Nginx + Apache</option>
                    </select>
                </div>
            </div>

            {{-- Document Root --}}
            <div class="form-group">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <label class="form-label" style="margin:0">Directorio raíz</label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-secondary);cursor:pointer;">
                        <input type="checkbox" wire:model.live="autoRoot" style="accent-color:var(--accent);">
                        Auto-generar
                    </label>
                </div>
                <input wire:model="document_root"
                       type="text"
                       class="form-input"
                       style="{{ $autoRoot ? 'opacity:0.6;' : '' }}"
                       {{ $autoRoot ? 'readonly' : '' }}
                       placeholder="/var/www/tudominio.com/public_html">
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                    <i class="fa-solid fa-info-circle"></i>
                    Se creará automáticamente en el servidor con permisos correctos.
                </div>
            </div>

            {{-- Nginx Config Preview --}}
            @if($name && strlen($name) > 3)
            <div style="margin-bottom:18px;">
                <div style="font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:8px;">
                    <i class="fa-solid fa-code"></i> Previsualización de configuración Nginx
                </div>
                <pre style="background:rgba(0,0,0,0.3);border:1px solid var(--glass-border);border-radius:8px;padding:14px;font-size:11px;color:var(--text-secondary);overflow-x:auto;line-height:1.6;">server {
    listen 80;
    server_name {{ $name ?: 'tudominio.com' }} www.{{ $name ?: 'tudominio.com' }};
    root {{ $document_root ?: '/var/www/'.($name ?: 'tudominio.com').'/public_html' }};
    index index.php index.html;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php{{ $php_version }}-fpm.sock;
        include fastcgi_params;
    }
}</pre>
            </div>
            @endif

            {{-- Submit --}}
            <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:8px;border-top:1px solid var(--glass-border);">
                <a href="{{ route('domains.index') }}" class="btn btn-ghost">
                    Cancelar
                </a>
                <button wire:click="save"
                        wire:loading.attr="disabled"
                        class="btn btn-primary">
                    <span wire:loading.remove>
                        <i class="fa-solid fa-rocket"></i> Crear Dominio
                    </span>
                    <span wire:loading>
                        <i class="fa-solid fa-spinner fa-spin"></i> Configurando...
                    </span>
                </button>
            </div>
        </div>

        {{-- Info Cards --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;">
            <div class="glass" style="padding:16px;">
                <div style="font-size:12px;font-weight:600;color:var(--success);margin-bottom:6px;">
                    <i class="fa-solid fa-check-circle"></i> Qué se crea automáticamente
                </div>
                <ul style="font-size:12px;color:var(--text-secondary);padding-left:16px;line-height:1.8;">
                    <li>Directorio <code>/var/www/dominio/</code></li>
                    <li>Config Nginx con PHP-FPM</li>
                    <li>Página de inicio por defecto</li>
                    <li>Symlink en sites-enabled</li>
                </ul>
            </div>
            <div class="glass" style="padding:16px;">
                <div style="font-size:12px;font-weight:600;color:var(--accent-light);margin-bottom:6px;">
                    <i class="fa-solid fa-info-circle"></i> Siguientes pasos
                </div>
                <ul style="font-size:12px;color:var(--text-secondary);padding-left:16px;line-height:1.8;">
                    <li>Apuntar DNS → IP del servidor</li>
                    <li>Activar SSL con Let's Encrypt</li>
                    <li>Subir archivos via FTP/SSH</li>
                    <li>Crear base de datos MySQL</li>
                </ul>
            </div>
        </div>
    </div>
</div>
