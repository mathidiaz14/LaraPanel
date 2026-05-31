<div>
    {{-- Messages --}}
    @if($successMessage)
        <div style="background:rgba(166,227,161,0.1);border:1px solid rgba(166,227,161,0.3);color:#a6e3a1;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px;">
            <i class="fa-solid fa-circle-check" style="margin-right:8px;"></i> {{ $successMessage }}
        </div>
    @endif
    @if($errorMessage)
        <div style="background:rgba(243,139,168,0.1);border:1px solid rgba(243,139,168,0.3);color:#f38ba8;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px;">
            <i class="fa-solid fa-triangle-exclamation" style="margin-right:8px;"></i> {{ $errorMessage }}
        </div>
    @endif

    <div style="display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start;">

        {{-- Left column: Server list --}}
        <div>
            <div class="glass" style="padding:16px;margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="font-size:14px;font-weight:700;color:var(--text-primary);">
                        <i class="fa-solid fa-server" style="color:#89b4fa;margin-right:6px;"></i> Servidores
                    </h3>
                    <button wire:click="$set('activeTab', 'add-server')" class="btn btn-primary btn-sm" style="padding:4px 8px;font-size:11px;">
                        <i class="fa-solid fa-plus"></i> Añadir
                    </button>
                </div>

                <div style="display:flex;flex-direction:column;gap:8px;">
                    @foreach($servers as $srv)
                        @php
                            $isSelected = $selectedServerId === $srv->id && $activeTab !== 'add-server';
                            $statusColor = $srv->isOnline() ? '#a6e3a1' : ($srv->status === 'offline' ? '#f38ba8' : '#f9e2af');
                        @endphp
                        <div wire:click="selectServer({{ $srv->id }})"
                             style="display:flex;align-items:center;justify-content:between;padding:10px 12px;border-radius:8px;border:1px solid {{ $isSelected ? 'rgba(137,180,250,0.4)' : 'var(--glass-border)' }};background:{{ $isSelected ? 'rgba(137,180,250,0.06)' : 'rgba(255,255,255,0.02)' }};cursor:pointer;transition:all 0.2s;text-align:left;">
                            
                            <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $statusColor }};box-shadow:0 0 8px {{ $statusColor }}88;flex-shrink:0;"></span>
                                <div style="min-width:0;flex:1;">
                                    <div style="font-size:13px;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        {{ $srv->name }}
                                        @if($srv->is_local)
                                            <span style="font-size:9px;opacity:0.6;margin-left:4px;">(Local)</span>
                                        @endif
                                    </div>
                                    <div style="font-size:11px;color:var(--text-muted);font-family:monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        {{ $srv->hostname }}
                                    </div>
                                </div>
                            </div>

                            @if($srv->latency_ms !== null)
                                <span style="font-size:10px;color:var(--text-muted);margin-left:8px;flex-shrink:0;">
                                    {{ $srv->latency_ms }} ms
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Right column: Active server detail / Add server --}}
        <div>
            @if($activeTab === 'add-server')
                {{-- Form: Add Server --}}
                <div class="glass" style="padding:24px;">
                    <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;color:var(--text-primary);">
                        <i class="fa-solid fa-circle-plus" style="color:#a6e3a1;margin-right:8px;"></i>
                        Añadir Servidor Remoto
                    </h3>

                    <form wire:submit.prevent="saveServer">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div>
                                <label class="form-label">Nombre Identificador</label>
                                <input type="text" wire:model="serverName" class="form-input" placeholder="ej. VPS Producción Novedad">
                                @error('serverName') <span style="color:var(--danger);font-size:11px;">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="form-label">Hostname / IP</label>
                                <input type="text" wire:model="hostname" class="form-input" placeholder="ej. 192.168.1.10 o vps.midominio.com">
                                @error('hostname') <span style="color:var(--danger);font-size:11px;">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:100px 1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div>
                                <label class="form-label">Puerto SSH</label>
                                <input type="number" wire:model="port" class="form-input">
                                @error('port') <span style="color:var(--danger);font-size:11px;">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="form-label">Usuario SSH</label>
                                <input type="text" wire:model="username" class="form-input">
                                @error('username') <span style="color:var(--danger);font-size:11px;">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="form-label">Tipo de Autenticación</label>
                                <select wire:model.live="authType" class="form-input" style="height:38px;">
                                    <option value="key">Clave SSH Privada (Recomendado)</option>
                                    <option value="password">Contraseña SSH</option>
                                </select>
                            </div>
                        </div>

                        @if($authType === 'key')
                            <div style="margin-bottom:16px;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                    <label class="form-label" style="margin-bottom:0;">Clave SSH Privada</label>
                                    <button type="button" wire:click="generateKeys" class="btn btn-ghost btn-sm" style="font-size:11px;padding:2px 8px;">
                                        <i class="fa-solid fa-key"></i> Generar claves automáticas
                                    </button>
                                </div>
                                <textarea wire:model="sshPrivateKey" class="form-input" rows="8" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;..." style="font-family:monospace;font-size:11px;line-height:1.4;"></textarea>
                                @if($generatedPublicKey)
                                    <div style="margin-top:12px;background:rgba(137,180,250,0.06);border:1px solid rgba(137,180,250,0.15);border-radius:8px;padding:12px 16px;">
                                        <div style="font-size:11px;font-weight:700;color:#89b4fa;margin-bottom:6px;">
                                            <i class="fa-solid fa-eye"></i> Clave Pública (agrega esta línea en <code>~/.ssh/authorized_keys</code> en el servidor remoto):
                                        </div>
                                        <textarea readonly onclick="this.select()" class="form-input" rows="3" style="font-family:monospace;font-size:10px;background:rgba(0,0,0,0.25);border-color:rgba(137,180,250,0.25);color:#cdd6f4;">{{ $generatedPublicKey }}</textarea>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div style="margin-bottom:16px;">
                                <label class="form-label">Contraseña SSH</label>
                                <input type="password" wire:model="sshPassword" class="form-input" placeholder="Ingresa contraseña de acceso">
                            </div>
                        @endif

                        <div style="margin-bottom:20px;">
                            <label class="form-label">Notas / Comentarios (opcional)</label>
                            <textarea wire:model="notes" class="form-input" rows="2" placeholder="ej. Servidor de base de datos MySQL en AWS"></textarea>
                        </div>

                        <div style="display:flex;gap:12px;justify-content:flex-end;">
                            <button type="button" wire:click="$set('activeTab', 'info')" class="btn btn-ghost">Cancelar</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar Servidor
                            </button>
                        </div>
                    </form>
                </div>
            @else
                {{-- Server Info & Subtabs --}}
                @if($selectedServer)
                    <div class="glass" style="padding:20px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <h2 style="font-size:18px;font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:10px;">
                                <i class="fa-solid fa-server" style="color:#89b4fa;"></i>
                                {{ $selectedServer->name }}
                                <span class="badge {{ $selectedServer->statusBadgeClass() }}" style="font-size:10px;padding:2px 8px;">
                                    <i class="fa-solid {{ $selectedServer->statusIcon() }}" style="margin-right:4px;"></i>
                                    {{ $selectedServer->status }}
                                </span>
                            </h2>
                            <div style="font-size:12px;color:var(--text-muted);font-family:monospace;margin-top:4px;">
                                {{ $selectedServer->connectionString() }}
                            </div>
                        </div>

                        @if(!$selectedServer->is_local)
                            <button wire:click="deleteServer({{ $selectedServer->id }})"
                                    class="btn btn-ghost"
                                    style="color:var(--danger);padding:8px;"
                                    onclick="return confirm('¿Estás seguro de que quieres eliminar este servidor? El panel central perderá el control sobre él.')">
                                <i class="fa-solid fa-trash-can"></i> Eliminar
                            </button>
                        @endif
                    </div>

                    {{-- Internal Subtabs Navigation --}}
                    <div style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;">
                        <button wire:click="$set('activeTab', 'info')" class="btn {{ $activeTab === 'info' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
                            <i class="fa-solid fa-chart-line"></i> Recursos & Info
                        </button>
                        <button wire:click="$set('activeTab', 'terminal')" class="btn {{ $activeTab === 'terminal' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
                            <i class="fa-solid fa-terminal"></i> Terminal SSH
                        </button>
                    </div>

                    {{-- SUBTAB Content: Resources Info --}}
                    @if($activeTab === 'info')
                        @if($selectedServer->status === 'online' && $selectedServer->os_info)
                            @php
                                $os = $selectedServer->os_info;
                                $cpuPercent = $os['cpuPercent'] ?? 0;
                                $ram = $os['ram'] ?? ['percent' => 0, 'total' => 0, 'used' => 0];
                                $disk = $os['disk'] ?? ['percent' => 0, 'total' => 0, 'used' => 0];
                            @endphp

                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
                                {{-- CPU --}}
                                <div class="glass" style="padding:16px;text-align:center;">
                                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;">Uso de CPU</div>
                                    <div style="font-size:28px;font-weight:700;color:var(--text-primary);margin-bottom:8px;">
                                        {{ $cpuPercent }}%
                                    </div>
                                    <div style="width:100%;height:8px;background:rgba(255,255,255,0.05);border-radius:4px;overflow:hidden;">
                                        <div style="width:{{ $cpuPercent }}%;height:100%;background:#89b4fa;border-radius:4px;transition:width 0.4s;"></div>
                                    </div>
                                </div>
                                {{-- RAM --}}
                                <div class="glass" style="padding:16px;text-align:center;">
                                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;">Memoria RAM</div>
                                    <div style="font-size:28px;font-weight:700;color:var(--text-primary);margin-bottom:2px;">
                                        {{ $ram['percent'] }}%
                                    </div>
                                    <div style="font-size:10px;color:var(--text-muted);margin-bottom:8px;">
                                        {{ $ram['used'] }} GB / {{ $ram['total'] }} GB
                                    </div>
                                    <div style="width:100%;height:8px;background:rgba(255,255,255,0.05);border-radius:4px;overflow:hidden;">
                                        <div style="width:{{ $ram['percent'] }}%;height:100%;background:#a6e3a1;border-radius:4px;transition:width 0.4s;"></div>
                                    </div>
                                </div>
                                {{-- Disk --}}
                                <div class="glass" style="padding:16px;text-align:center;">
                                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;">Almacenamiento (/)</div>
                                    <div style="font-size:28px;font-weight:700;color:var(--text-primary);margin-bottom:2px;">
                                        {{ $disk['percent'] }}%
                                    </div>
                                    <div style="font-size:10px;color:var(--text-muted);margin-bottom:8px;">
                                        {{ $disk['used'] }} GB / {{ $disk['total'] }} GB
                                    </div>
                                    <div style="width:100%;height:8px;background:rgba(255,255,255,0.05);border-radius:4px;overflow:hidden;">
                                        <div style="width:{{ $disk['percent'] }}%;height:100%;background:#f9e2af;border-radius:4px;transition:width 0.4s;"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Detailed System Info --}}
                            <div class="glass" style="padding:20px;">
                                <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--text-primary);">
                                    <i class="fa-solid fa-circle-info"></i> Detalles del Sistema
                                </h3>

                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.03);">
                                        <span style="font-size:13px;color:var(--text-muted);">Kernel / OS</span>
                                        <span style="font-size:13px;font-weight:600;color:var(--text-primary);font-family:monospace;">{{ $os['kernel'] }}</span>
                                    </div>
                                    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.03);">
                                        <span style="font-size:13px;color:var(--text-muted);">Uptime</span>
                                        <span style="font-size:13px;font-weight:600;color:var(--text-primary);">{{ $os['uptime'] }}</span>
                                    </div>
                                    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.03);">
                                        <span style="font-size:13px;color:var(--text-muted);">Latencia de control</span>
                                        <span style="font-size:13px;font-weight:600;color:var(--text-primary);">{{ $selectedServer->latency_ms ?? 0 }} ms</span>
                                    </div>
                                    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.03);">
                                        <span style="font-size:13px;color:var(--text-muted);">Última comprobación</span>
                                        <span style="font-size:13px;font-weight:600;color:var(--text-primary);">{{ $selectedServer->last_ping_at ? $selectedServer->last_ping_at->format('d/m/Y H:i:s') : 'Nunca' }}</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="glass" style="padding:48px;text-align:center;">
                                <i class="fa-solid fa-circle-exclamation" style="font-size:48px;color:#f38ba8;margin-bottom:16px;"></i>
                                <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);">Servidor inaccesible</h3>
                                <p style="font-size:13px;color:var(--text-muted);max-width:400px;margin:8px auto 20px;">
                                    El panel central no puede establecer conexión SSH con este servidor remoto. Verifica las credenciales, el firewall o la IP.
                                </p>
                                <button wire:click="pingServer({{ $selectedServer->id }}, '{{ App\Services\ServerService::class }}')" class="btn btn-ghost btn-sm">
                                    <i class="fa-solid fa-rotate-right"></i> Reintentar Conexión
                                </button>
                            </div>
                        @endif
                    @endif

                    {{-- SUBTAB Content: Remote Terminal --}}
                    @if($activeTab === 'terminal')
                        <div class="glass" style="padding:20px;">
                            <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;color:var(--text-primary);">
                                <i class="fa-solid fa-terminal"></i> Ejecución Rápida de Comandos (SSH)
                            </h3>
                            <p style="font-size:11px;color:var(--text-muted);margin-bottom:16px;">
                                Ejecuta comandos individuales contra este servidor de forma aislada y segura.
                            </p>

                            <form wire:submit.prevent="runTerminalCommand" style="display:flex;gap:8px;margin-bottom:16px;">
                                <input type="text" wire:model="terminalCommand" class="form-input" placeholder="ej. docker ps, ls -la /var/www, systemctl status nginx" style="font-family:monospace;font-size:12px;">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="runTerminalCommand">
                                    <span wire:loading.remove wire:target="runTerminalCommand">Ejecutar</span>
                                    <span wire:loading wire:target="runTerminalCommand"><i class="fa-solid fa-spinner fa-spin"></i></span>
                                </button>
                            </form>

                            @if($terminalOutput)
                                <pre style="background:rgba(0,0,0,0.85);border:1px solid var(--glass-border);border-radius:8px;padding:16px;font-family:monospace;font-size:11px;color:#a6e3a1;line-height:1.6;white-space:pre-wrap;max-height:350px;overflow-y:auto;text-align:left;">{{ $terminalOutput }}</pre>
                            @endif
                        </div>
                    @endif
                @endif
            @endif
        </div>

    </div>
</div>
