<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">PHP Multi-Version Manager</h1>
            <p style="color:var(--text-secondary);font-size:13px;">
                Administre múltiples versiones de PHP-FPM, configure php.ini y cambie la versión de sus dominios en tiempo real.
            </p>
        </div>
    </div>

    {{-- Alerts --}}
    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    {{-- PHP Versions Cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:28px;">
        @foreach($phpVersions as $php)
        @php
            $cardBorder = $php['active'] ? 'rgba(16,185,129,0.25)' : 'rgba(255,255,255,0.08)';
            $glow = $php['active'] ? 'box-shadow: 0 0 15px rgba(16, 185, 129, 0.05);' : '';
        @endphp
        <div class="glass" style="padding:20px;border-color:{{ $cardBorder }};position:relative; {{ $glow }}">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;border-radius:10px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-brands fa-php" style="color:var(--accent-light);font-size:20px;"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:16px;color:var(--text-primary);">PHP {{ $php['version'] }}</div>
                        <div style="font-size:11px;color:var(--text-muted);">{{ $php['service'] }}</div>
                    </div>
                </div>
                
                {{-- Status --}}
                <div>
                    @if($php['active'])
                    <span class="badge badge-success" style="display:inline-flex;align-items:center;gap:5px;">
                        <span style="width:6px;height:6px;border-radius:50%;background:#10b981;display:inline-block;animation:pulse 2s infinite;"></span>
                        Activo
                    </span>
                    @else
                    <span class="badge badge-muted" style="display:inline-flex;align-items:center;gap:5px;">
                        <span style="width:6px;height:6px;border-radius:50%;background:var(--text-muted);display:inline-block;"></span>
                        Inactivo
                    </span>
                    @endif
                </div>
            </div>

            {{-- Info Stats --}}
            <div style="display:grid;grid-template-columns:1fr;gap:10px;margin-bottom:16px;background:rgba(0,0,0,0.15);padding:12px;border-radius:8px;border:1px solid var(--glass-border);">
                <div style="display:flex;justify-content:space-between;font-size:12px;">
                    <span style="color:var(--text-secondary);">Dominios asignados:</span>
                    <strong style="color:var(--text-primary);">{{ $php['domains_count'] }}</strong>
                </div>
            </div>

            {{-- Actions --}}
            <div style="display:flex;gap:8px;">
                <button wire:click="selectVersion('{{ $php['version'] }}')" class="btn btn-ghost btn-sm" style="flex:1;justify-content:center;">
                    <i class="fa-solid fa-sliders"></i> php.ini
                </button>
                <button wire:click="restartPhp('{{ $php['version'] }}')" class="btn btn-ghost btn-sm" style="color:var(--warning);border-color:rgba(245,158,11,0.25);flex:1;justify-content:center;">
                    <i class="fa-solid fa-rotate"></i> Reiniciar
                </button>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Domain Assignment Table --}}

    {{-- FPM Pool Process Status --}}
    <div class="glass" style="padding:20px;margin-bottom:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h2 style="font-size:15px;font-weight:700;margin:0;">
                <i class="fa-solid fa-microchip" style="color:var(--accent-light);margin-right:8px;"></i>
                Estado de Pools FPM en Tiempo Real
            </h2>
            <button wire:click="refreshAll" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-sync" wire:loading.class="fa-spin" wire:target="refreshAll"></i> Actualizar
            </button>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;">
            @foreach($phpVersions as $php)
            <div style="background:rgba(0,0,0,0.2);border:1px solid {{ $php['active'] ? 'rgba(16,185,129,0.25)' : 'var(--glass-border)' }};border-radius:10px;padding:14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-weight:700;font-size:14px;font-family:monospace;">PHP {{ $php['version'] }}</span>
                    @if($php['active'])
                    <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:var(--success);">
                        <span style="width:7px;height:7px;border-radius:50%;background:var(--success);display:inline-block;animation:pulse 2s infinite;"></span>
                        Corriendo
                    </span>
                    @else
                    <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:var(--text-muted);">
                        <span style="width:7px;height:7px;border-radius:50%;background:var(--text-muted);display:inline-block;"></span>
                        Detenido
                    </span>
                    @endif
                </div>
                <div style="font-size:11px;color:var(--text-secondary);">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                        <span>Servicio</span>
                        <code>{{ $php['service'] }}</code>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                        <span>Dominios</span>
                        <strong style="color:var(--text-primary);">{{ $php['domains_count'] }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span>Socket</span>
                        <code style="font-size:10px;">/run/php/php{{ $php['version'] }}-fpm.sock</code>
                    </div>
                </div>
                <div style="margin-top:10px;display:flex;gap:6px;">
                    @if($php['active'])
                    <button wire:click="restartPhp('{{ $php['version'] }}')" class="btn btn-ghost btn-sm" style="flex:1;justify-content:center;font-size:11px;padding:4px;">
                        <i class="fa-solid fa-rotate" style="color:var(--warning);"></i> Restart
                    </button>
                    @else
                    <button wire:click="restartPhp('{{ $php['version'] }}')" class="btn btn-ghost btn-sm" style="flex:1;justify-content:center;font-size:11px;padding:4px;color:var(--success);">
                        <i class="fa-solid fa-play"></i> Iniciar
                    </button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Domain Assignment Table --}}

    <div class="glass" style="padding:24px;">
        <h2 style="font-size:16px;font-weight:700;margin-bottom:6px;">Asignación de Versión por Dominio</h2>
        <p style="color:var(--text-secondary);font-size:12px;margin-bottom:20px;">
            Cambie al instante la versión de PHP en cualquiera de sus dominios activos. El servidor web se actualizará y reiniciará automáticamente.
        </p>

        @php
            $allDomains = \App\Models\Domain::where('user_id', auth()->id())->where('is_active', true)->orderBy('name')->get();
        @endphp

        @if($allDomains->isEmpty())
        <div style="text-align:center;padding:40px;color:var(--text-muted);">
            <i class="fa-solid fa-globe" style="font-size:32px;opacity:0.3;margin-bottom:10px;display:block;"></i>
            No hay dominios activos creados todavía.
        </div>
        @else
        <div style="overflow-x:auto;">
            <table class="table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Dominio</th>
                        <th>Tipo</th>
                        <th>Versión Actual</th>
                        <th style="text-align:right;">Cambiar Versión</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allDomains as $domain)
                    <tr>
                        <td>
                            <strong style="color:var(--text-primary);">{{ $domain->name }}</strong>
                            <div style="font-size:11px;color:var(--text-muted);">{{ $domain->document_root }}</div>
                        </td>
                        <td>
                            <span class="badge badge-muted">{{ strtoupper($domain->type) }}</span>
                        </td>
                        <td>
                            <span class="badge badge-accent" style="font-family:monospace;font-size:12px;">
                                PHP {{ $domain->php_version }}
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <select class="form-input" style="width:auto;display:inline-block;padding:4px 10px;font-size:12px;"
                                    wire:change="changeDomainPhp({{ $domain->id }}, $event.target.value)">
                                @foreach(config('larapanel.server.php_versions') as $v)
                                <option value="{{ $v }}" {{ $domain->php_version === $v ? 'selected' : '' }}>
                                    PHP {{ $v }}
                                </option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- php.ini Edit Modal --}}
    @if($selectedVersion)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="max-width:540px;width:100%;padding:28px;margin:16px;">
            <div style="display:flex;justify-content:between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;">
                <h3 style="font-size:16px;font-weight:700;margin:0;">
                    <i class="fa-solid fa-sliders" style="color:var(--accent-light);margin-right:8px;"></i>
                    Configurar php.ini para PHP {{ $selectedVersion }}
                </h3>
                <button wire:click="closeSettings" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;margin-left:auto;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                
                {{-- Memory Limit --}}
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">memory_limit</label>
                    <select wire:model="settings.memory_limit" class="form-input" style="padding:6px 10px;font-size:13px;">
                        <option value="64M">64M</option>
                        <option value="128M">128M</option>
                        <option value="256M">256M</option>
                        <option value="512M">512M</option>
                        <option value="1024M">1024M</option>
                    </select>
                </div>

                {{-- Max Execution Time --}}
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">max_execution_time</label>
                    <select wire:model="settings.max_execution_time" class="form-input" style="padding:6px 10px;font-size:13px;">
                        <option value="30">30 s</option>
                        <option value="60">60 s</option>
                        <option value="120">120 s</option>
                        <option value="300">300 s</option>
                        <option value="600">600 s</option>
                    </select>
                </div>

                {{-- Upload Max Filesize --}}
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">upload_max_filesize</label>
                    <select wire:model="settings.upload_max_filesize" class="form-input" style="padding:6px 10px;font-size:13px;">
                        <option value="2M">2M</option>
                        <option value="10M">10M</option>
                        <option value="50M">50M</option>
                        <option value="100M">100M</option>
                        <option value="500M">500M</option>
                    </select>
                </div>

                {{-- Post Max Size --}}
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">post_max_size</label>
                    <select wire:model="settings.post_max_size" class="form-input" style="padding:6px 10px;font-size:13px;">
                        <option value="8M">8M</option>
                        <option value="16M">16M</option>
                        <option value="64M">64M</option>
                        <option value="128M">128M</option>
                        <option value="512M">512M</option>
                    </select>
                </div>

                {{-- Display Errors --}}
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">display_errors</label>
                    <select wire:model="settings.display_errors" class="form-input" style="padding:6px 10px;font-size:13px;">
                        <option value="Off">Off (Producción)</option>
                        <option value="On">On (Desarrollo)</option>
                    </select>
                </div>

                {{-- Short Open Tag --}}
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">short_open_tag</label>
                    <select wire:model="settings.short_open_tag" class="form-input" style="padding:6px 10px;font-size:13px;">
                        <option value="Off">Off</option>
                        <option value="On">On</option>
                    </select>
                </div>

            </div>

            <div style="background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.2);border-radius:8px;padding:12px;margin-bottom:20px;font-size:11px;color:var(--text-secondary);">
                <i class="fa-solid fa-info-circle" style="color:var(--warning);margin-right:6px;"></i>
                Al guardar, LaraPanel escribirá los valores en el archivo <code>99-larapanel.ini</code> y reiniciará el servicio <code>php{{ $selectedVersion }}-fpm</code> automáticamente.
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button wire:click="closeSettings" class="btn btn-ghost btn-sm" wire:loading.attr="disabled">Cancelar</button>
                <button wire:click="saveSettings" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span wire:loading.remove>Guardar y Aplicar</span>
                    <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Guardando...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    <div wire:loading.delay style="position:fixed;bottom:24px;right:24px;z-index:300;">
        <div class="glass" style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-spinner fa-spin"></i> Procesando cambios...
        </div>
    </div>

    <style>
        @keyframes pulse {
            0% { transform: scale(0.9); opacity: 0.8; }
            50% { transform: scale(1.15); opacity: 1; }
            100% { transform: scale(0.9); opacity: 0.8; }
        }
    </style>
</div>
