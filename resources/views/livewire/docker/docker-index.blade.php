<div>
    {{-- Tabs Navigation --}}
    <div style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;">
        <button wire:click="setTab('containers')" class="btn {{ $activeTab === 'containers' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-box"></i> Contenedores
        </button>
        <button wire:click="setTab('images')" class="btn {{ $activeTab === 'images' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-tags"></i> Imágenes
        </button>
        <button wire:click="setTab('compose')" class="btn {{ $activeTab === 'compose' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-cubes"></i> Docker Compose
        </button>
        <button wire:click="setTab('marketplace')" class="btn {{ $activeTab === 'marketplace' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-store"></i> Marketplace
        </button>
        <button wire:click="setTab('deploy')" class="btn {{ $activeTab === 'deploy' ? 'btn-primary' : 'btn-ghost' }} btn-sm" style="margin-left:auto;">
            <i class="fa-solid fa-rocket"></i> Desplegar App
        </button>
    </div>

    @if(!$daemonRunning)
        <div class="glass lp-panel" style="text-align:center;padding:40px;">
            <i class="fa-brands fa-docker" style="font-size:48px;color:#2496ed;margin-bottom:16px;opacity:0.5;"></i>
            <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;color:var(--text-primary);">Docker no está corriendo</h3>
            <p style="color:var(--text-secondary);font-size:13px;max-width:500px;margin:0 auto;">
                El daemon de Docker no está activo o el usuario del panel no tiene permisos para acceder al socket de Docker (<code>/var/run/docker.sock</code>).
            </p>
        </div>
    @else
        {{-- TAB: CONTAINERS --}}
        @if($activeTab === 'containers')
            <div style="display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start;">
                {{-- Sidebar: Containers list --}}
                <div class="glass lp-panel" style="padding:16px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                        <h2 class="panel-title" style="margin:0;">Contenedores</h2>
                        <button wire:click="loadContainers" class="btn btn-ghost btn-sm" title="Refrescar Lista">
                            <i class="fa-solid fa-rotate"></i>
                        </button>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:8px;max-height:600px;overflow-y:auto;padding-right:4px;">
                        @forelse($groupedContainers as $prefix => $group)
                            {{-- Group header --}}
                            <div style="display:flex;align-items:center;gap:8px;margin-top:8px;margin-bottom:4px;padding:0 4px;">
                                <i class="fa-brands fa-docker" style="font-size:11px;color:#2496ed;opacity:0.8;"></i>
                                <span style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;">
                                    {{ $prefix }}
                                </span>
                                <div style="flex:1;height:1px;background:var(--glass-border);opacity:0.5;"></div>
                                <span style="font-size:10px;color:var(--text-muted);opacity:0.7;">{{ count($group) }}</span>
                            </div>

                            @foreach($group as $c)
                                @php
                                    $isRunning = str_contains(strtolower($c['state']), 'running');
                                    $isPaused  = str_contains(strtolower($c['state']), 'paused');
                                    $isSelected = $selectedContainer && $selectedContainer['name'] === $c['name'];
                                    $borderColor = $isSelected ? 'rgba(99,102,241,0.5)' : 'var(--glass-border)';
                                    $bgColor     = $isSelected ? 'rgba(99,102,241,0.1)' : 'rgba(255,255,255,0.02)';
                                    // Show only the suffix (strip the prefix-)
                                    $suffix = str_contains($c['name'], '-')
                                        ? substr($c['name'], strlen($prefix) + 1)
                                        : $c['name'];
                                @endphp
                                <button wire:click="selectContainer('{{ $c['name'] }}')"
                                    style="width:100%;text-align:left;padding:10px 12px;border-radius:8px;border:1px solid {{ $borderColor }};background:{{ $bgColor }};cursor:pointer;transition:all 0.2s;margin-bottom:4px;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <div style="width:6px;height:6px;border-radius:50%;background:{{ $isRunning ? '#22c55e' : ($isPaused ? '#f59e0b' : '#6b7280') }};flex-shrink:0;"></div>
                                            <span style="font-size:12px;font-weight:600;color:var(--text-primary);text-overflow:ellipsis;white-space:nowrap;overflow:hidden;max-width:160px;">
                                                {{ $suffix }}
                                            </span>
                                        </div>
                                        <span class="badge {{ $isRunning ? 'badge-success' : ($isPaused ? 'badge-warning' : 'badge-secondary') }}" style="font-size:9px;padding:2px 5px;">
                                            {{ $c['state'] }}
                                        </span>
                                    </div>
                                    <div style="font-size:10px;color:var(--text-muted);text-overflow:ellipsis;white-space:nowrap;overflow:hidden;padding-left:12px;">
                                        {{ $c['image'] }}
                                    </div>
                                    @if($c['ports'])
                                        <div style="font-size:10px;color:var(--text-muted);font-family:monospace;padding-left:12px;margin-top:2px;">
                                            {{ $c['ports'] }}
                                        </div>
                                    @endif
                                </button>
                            @endforeach
                        @empty
                            <div style="text-align:center;padding:20px 10px;color:var(--text-muted);font-size:12px;">
                                No hay contenedores creados.
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Container Details and Control --}}
                <div>
                    @if($actionOutput)
                        <div class="alert alert-info" style="margin-bottom:20px;">
                            {{ $actionOutput }}
                        </div>
                    @endif

                    @if($selectedContainer)
                        <div class="glass lp-panel" style="margin-bottom:20px;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                                <div>
                                    <h2 class="panel-title" style="font-size:20px;margin-bottom:4px;">
                                        <i class="fa-solid fa-box" style="color:#2496ed;margin-right:8px;"></i>
                                        {{ $selectedContainer['name'] }}
                                    </h2>
                                    <div style="font-size:12px;color:var(--text-muted);font-family:monospace;">
                                        ID: {{ substr($selectedContainer['id'], 0, 12) }} | Imagen: {{ $selectedContainer['image'] }}
                                    </div>
                                </div>

                                <div style="display:flex;gap:8px;">
                                    @if(str_contains(strtolower($selectedContainer['state']), 'running'))
                                        <button wire:click="stopContainer('{{ $selectedContainer['name'] }}')" class="btn btn-warning btn-sm" title="Detener">
                                            <i class="fa-solid fa-stop"></i> Detener
                                        </button>
                                    @else
                                        <button wire:click="startContainer('{{ $selectedContainer['name'] }}')" class="btn btn-success btn-sm" title="Iniciar">
                                            <i class="fa-solid fa-play"></i> Iniciar
                                        </button>
                                    @endif

                                    <button wire:click="restartContainer('{{ $selectedContainer['name'] }}')" class="btn btn-ghost btn-sm" title="Reiniciar">
                                        <i class="fa-solid fa-rotate-right"></i> Reiniciar
                                    </button>

                                    <button wire:click="viewLogs('{{ $selectedContainer['name'] }}')" class="btn btn-primary btn-sm" title="Ver logs">
                                        <i class="fa-solid fa-file-signature"></i> Logs
                                    </button>

                                    <button wire:click="openTerminal('{{ $selectedContainer['name'] }}')" class="btn btn-primary btn-sm" style="background:var(--accent-light);border-color:var(--accent-light);color:black;" title="Abrir Consola">
                                        <i class="fa-solid fa-terminal"></i> Consola
                                    </button>

                                    <button wire:click="removeContainer('{{ $selectedContainer['name'] }}')" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar contenedor? Se detendrá si está corriendo.')" title="Eliminar">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:24px;">
                                <div class="glass" style="padding:16px;background:rgba(255,255,255,0.01);">
                                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">Estado actual</div>
                                    <div style="font-size:16px;font-weight:600;color:var(--text-primary);">
                                        {{ ucfirst($selectedContainer['state']) }}
                                    </div>
                                </div>
                                <div class="glass" style="padding:16px;background:rgba(255,255,255,0.01);">
                                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">Detalle de estado</div>
                                    <div style="font-size:13px;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        {{ $selectedContainer['status'] }}
                                    </div>
                                </div>
                                <div class="glass" style="padding:16px;background:rgba(255,255,255,0.01);">
                                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">Puertos Mapeados</div>
                                    <div style="font-size:12px;font-family:monospace;color:var(--text-primary);">
                                        {{ $selectedContainer['ports'] ?: '—' }}
                                    </div>
                                </div>
                                <div class="glass" style="padding:16px;background:rgba(255,255,255,0.01);">
                                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">Fecha Creación</div>
                                    <div style="font-size:12px;color:var(--text-primary);">
                                        {{ $selectedContainer['created'] }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Logs Terminal Panel --}}
                        @if($showLogs)
                            <div class="glass lp-panel">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                    <h3 class="panel-title" style="margin:0;"><i class="fa-solid fa-terminal"></i> Terminal Logs</h3>
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <span style="font-size:11px;color:var(--text-muted);">Líneas:</span>
                                            <select wire:model.live="logLines" class="form-input" style="padding:4px;font-size:11px;width:70px;background:rgba(0,0,0,0.3);">
                                                <option value="50">50</option>
                                                <option value="100">100</option>
                                                <option value="250">250</option>
                                                <option value="500">500</option>
                                            </select>
                                        </div>
                                        <button wire:click="refreshLogs" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate"></i> Refrescar</button>
                                    </div>
                                </div>
                                <pre style="background:rgba(0,0,0,0.8);border:1px solid var(--glass-border);border-radius:8px;padding:16px;font-family:monospace;font-size:11px;color:#89b4fa;line-height:1.6;white-space:pre-wrap;max-height:400px;overflow-y:auto;text-align:left;">{{ $containerLogs ?: 'No hay logs de salida o el contenedor no ha generado output.' }}</pre>
                            </div>
                        @endif
                    @else
                        <div class="glass lp-panel" style="padding:80px 20px;text-align:center;">
                            <i class="fa-solid fa-cube" style="font-size:48px;opacity:0.2;margin-bottom:16px;display:block;"></i>
                            <h3 style="font-size:16px;font-weight:700;margin-bottom:8px;color:var(--text-primary);">Gestión de Contenedores</h3>
                            <p style="color:var(--text-secondary);font-size:13px;max-width:400px;margin:0 auto;">
                                Selecciona un contenedor de la lista para ver sus detalles, controlar su ejecución, ver el uso de recursos y sus logs.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- TAB: IMAGES --}}
        @if($activeTab === 'images')
            <div style="display:grid;grid-template-columns:1fr;gap:24px;">
                {{-- Downloader image form --}}
                <div class="glass lp-panel">
                    <h3 class="panel-title"><i class="fa-solid fa-cloud-arrow-down"></i> Descargar Imagen (Pull Image)</h3>
                    <form wire:submit.prevent="pullImageAction">
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <div style="flex:1;">
                                <input type="text" wire:model="pullImage" class="form-input" placeholder="ej. nginx:alpine o mysql:8.0">
                                @error('pullImage') <span style="color:var(--danger);font-size:11px;display:block;margin-top:4px;">{{ $message }}</span> @enderror
                            </div>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove><i class="fa-solid fa-download"></i> Descargar</span>
                                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Descargando...</span>
                            </button>
                        </div>
                    </form>

                    @if($pullOutput)
                        <div style="margin-top:16px;">
                            <pre style="background:rgba(0,0,0,0.5);border:1px solid var(--glass-border);border-radius:8px;padding:16px;font-family:monospace;font-size:11px;color:#cdd6f4;line-height:1.6;white-space:pre-wrap;max-height:200px;overflow-y:auto;">{{ $pullOutput }}</pre>
                        </div>
                    @endif
                </div>

                {{-- Local Images table --}}
                <div class="glass lp-panel">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <h3 class="panel-title" style="margin:0;"><i class="fa-solid fa-compact-disc"></i> Imágenes Locales</h3>
                        <button wire:click="pruneImages" class="btn btn-ghost btn-sm" onclick="return confirm('¿Eliminar todas las imágenes sin usar para liberar espacio?')">
                            <i class="fa-solid fa-broom"></i> Limpiar no usadas (Prune)
                        </button>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="lp-table">
                            <thead>
                                <tr>
                                    <th>Imagen / Repositorio</th>
                                    <th>Tag</th>
                                    <th>ID Imagen</th>
                                    <th>Tamaño</th>
                                    <th>Creada</th>
                                    <th style="text-align:right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($images as $img)
                                    <tr>
                                        <td style="font-weight:600;color:var(--text-primary);">{{ $img['repo'] }}</td>
                                        <td><span class="badge badge-secondary" style="font-size:10px;">{{ $img['tag'] }}</span></td>
                                        <td style="font-family:monospace;font-size:11px;">{{ substr($img['id'], 0, 12) }}</td>
                                        <td>{{ $img['size'] }}</td>
                                        <td style="font-size:11px;color:var(--text-muted);">{{ $img['created'] }}</td>
                                        <td style="text-align:right;">
                                            <button wire:click="removeImage('{{ $img['repo'] }}:{{ $img['tag'] }}')" class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="return confirm('¿Deseas eliminar la imagen?')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach

                                @if(empty($images))
                                    <tr>
                                        <td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted);">
                                            No hay imágenes descargadas.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- TAB: COMPOSE --}}
        @if($activeTab === 'compose')
            <div style="display:grid;grid-template-columns:1fr;gap:24px;">
                <div class="glass lp-panel">
                    <h3 class="panel-title"><i class="fa-solid fa-cube"></i> Docker Compose Stacks</h3>
                    
                    <form wire:submit.prevent="deployCompose">
                        <div style="margin-bottom:16px;">
                            <label class="form-label">Nombre del Stack</label>
                            <input type="text" wire:model="composeName" class="form-input" placeholder="ej. mi-nginx-app">
                            @error('composeName') <span style="color:var(--danger);font-size:11px;display:block;margin-top:4px;">{{ $message }}</span> @enderror
                        </div>

                        <div style="margin-bottom:20px;">
                            <label class="form-label">Contenido docker-compose.yml</label>
                            <textarea wire:model="composeContent" class="form-input" rows="12" style="font-family:monospace;font-size:12px;line-height:1.6;background:rgba(0,0,0,0.3);color:#a6e3a1;"></textarea>
                            @error('composeContent') <span style="color:var(--danger);font-size:11px;display:block;margin-top:4px;">{{ $message }}</span> @enderror
                        </div>

                        <div style="display:flex;justify-content:flex-end;gap:12px;">
                            <button type="button" wire:click="viewComposeLogs" class="btn btn-ghost" {{ empty($composeName) ? 'disabled' : '' }}>
                                <i class="fa-solid fa-file-lines"></i> Logs del Stack
                            </button>
                            <button type="button" wire:click="stopCompose" class="btn btn-danger" {{ empty($composeName) ? 'disabled' : '' }} onclick="return confirm('¿Detener y eliminar todos los contenedores de este stack?')">
                                <i class="fa-solid fa-stop"></i> Detener Stack
                            </button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove><i class="fa-solid fa-rocket"></i> Desplegar / Actualizar</span>
                                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Desplegando...</span>
                            </button>
                        </div>
                    </form>
                </div>

                @if($composeOutput)
                    <div class="glass lp-panel">
                        <h4 class="panel-title"><i class="fa-solid fa-terminal"></i> Salida de Comandos</h4>
                        <pre style="background:rgba(0,0,0,0.8);border:1px solid var(--glass-border);border-radius:8px;padding:16px;font-family:monospace;font-size:11px;color:#cdd6f4;line-height:1.6;white-space:pre-wrap;max-height:300px;overflow-y:auto;text-align:left;">{{ $composeOutput }}</pre>
                    </div>
                @endif
            </div>
        {{-- TAB: DEPLOY --}}
        @elseif($activeTab === 'deploy')
            <div class="glass lp-panel" style="max-width:800px;margin:0 auto;">
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
                    <div style="width:48px;height:48px;border-radius:12px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-rocket" style="font-size:24px;color:var(--accent-light);"></i>
                    </div>
                    <div>
                        <h2 style="font-size:18px;font-weight:700;color:var(--text-primary);">Desplegar Aplicación Docker</h2>
                        <p style="font-size:13px;color:var(--text-muted);margin-top:4px;">Inicia contenedores desde un directorio y asígnales un dominio con proxy inverso (Nginx).</p>
                    </div>
                </div>

                <form wire:submit.prevent="deployApp" style="display:flex;flex-direction:column;gap:16px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:8px;color:var(--text-secondary);">Ruta del Proyecto (donde está el docker-compose.yml)</label>
                            <div style="position:relative;">
                                <i class="fa-solid fa-folder" style="position:absolute;left:12px;top:10px;color:var(--text-muted);z-index:2;"></i>
                                <input type="text" wire:model.defer="deployPath" class="form-input" placeholder="/var/www/panel/html/mi-app" style="padding-left:36px;width:100%;margin:0;">
                            </div>
                            @error('deployPath') <span style="color:var(--danger);font-size:11px;margin-top:4px;display:block;">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:8px;color:var(--text-secondary);">Dominio o Subdominio Público</label>
                            <div style="position:relative;">
                                <i class="fa-solid fa-globe" style="position:absolute;left:12px;top:10px;color:var(--text-muted);z-index:2;"></i>
                                <select wire:model.defer="deployDomain" class="form-input" style="padding-left:36px;width:100%;appearance:none;cursor:pointer;margin:0;">
                                    <option value="">Selecciona un dominio...</option>
                                    @foreach($availableDomains as $dom)
                                        <option value="{{ $dom }}">{{ $dom }}</option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down" style="position:absolute;right:12px;top:12px;color:var(--text-muted);font-size:12px;pointer-events:none;z-index:2;"></i>
                            </div>
                            @error('deployDomain') <span style="color:var(--danger);font-size:11px;margin-top:4px;display:block;">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="form-group" style="background:rgba(0,0,0,0.1);padding:16px;border-radius:8px;border:1px solid rgba(255,255,255,0.05);margin-top:8px;">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:8px;color:var(--text-secondary);">Puerto Interno del Contenedor (expuesto al host)</label>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="position:relative;flex:1;max-width:150px;">
                                <i class="fa-solid fa-plug" style="position:absolute;left:12px;top:10px;color:var(--text-muted);z-index:2;"></i>
                                <input type="number" wire:model.defer="deployPort" class="form-input" placeholder="3000" style="padding-left:36px;width:100%;margin:0;">
                            </div>
                            <p style="font-size:11px;color:var(--text-muted);margin:0;line-height:1.4;">
                                LaraPanel creará un proxy inverso desde <br><code style="color:var(--accent-light);background:transparent;padding:0;">http://{dominio}</code> hacia <code style="color:var(--accent-light);background:transparent;padding:0;">http://127.0.0.1:{PUERTO}</code>.
                            </p>
                        </div>
                        @error('deployPort') <span style="color:var(--danger);font-size:11px;margin-top:4px;display:block;">{{ $message }}</span> @enderror
                    </div>

                    <div style="margin-top:16px;display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="deployApp">
                            <i class="fa-solid fa-rocket" wire:loading.remove wire:target="deployApp"></i>
                            <i class="fa-solid fa-spinner fa-spin" wire:loading wire:target="deployApp"></i>
                            Desplegar y Enlazar Dominio
                        </button>
                    </div>
                </form>

                @if($deployOutput)
                    <div style="margin-top:24px;">
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:8px;color:var(--text-secondary);">Resultado de la Operación</label>
                        <pre style="background:rgba(0,0,0,0.3);padding:16px;border-radius:8px;border:1px solid var(--glass-border);font-family:monospace;font-size:12px;color:#a8b2d1;white-space:pre-wrap;max-height:400px;overflow-y:auto;">{{ $deployOutput }}</pre>
                    </div>
                @endif
            </div>
        @elseif($activeTab === 'marketplace')
            <div>
                <div style="margin-bottom:20px;">
                    <h2 style="font-size:18px;font-weight:700;color:var(--text-primary);margin-bottom:4px;">
                        <i class="fa-solid fa-store" style="color:var(--accent-light);margin-right:8px;"></i>
                        Docker Marketplace
                    </h2>
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">
                        Selecciona una plantilla de Docker Compose preconfigurada para desplegarla en tu servidor al instante con un solo clic.
                    </p>
                </div>

                <div class="lp-two-col">
                    @foreach($marketplaceTemplates as $key => $tpl)
                        <div class="glass lp-panel" style="display:flex;flex-direction:column;justify-content:space-between;transition:all 0.2s;" 
                             onmouseover="this.style.transform='translateY(-4px)';this.style.borderColor='rgba(99,102,241,0.4)';" 
                             onmouseout="this.style.transform='none';this.style.borderColor='var(--glass-border)';">
                            <div>
                                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
                                    <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--accent-light);flex-shrink:0;">
                                        <i class="{{ $tpl['icon'] }}"></i>
                                    </div>
                                    <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);margin:0;">{{ $tpl['name'] }}</h3>
                                </div>
                                <p style="font-size:12px;color:var(--text-secondary);line-height:1.5;margin-bottom:20px;min-height:54px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">
                                    {{ $tpl['desc'] }}
                                </p>
                            </div>
                            <button wire:click="selectMarketplaceTemplate('{{ $key }}')" class="btn btn-primary btn-sm" style="width:100%;justify-content:center;background:linear-gradient(135deg, var(--accent), var(--accent-light));border:none;color:black;font-weight:600;">
                                <i class="fa-solid fa-code"></i> Usar plantilla
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    {{-- Terminal Modal --}}
    @if($showTerminal)
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated" style="max-width:800px;">
            <div class="lp-modal-header">
                <h3 class="panel-title" style="margin:0;color:var(--accent-light);">
                    <i class="fa-solid fa-terminal"></i> Consola: {{ $terminalContainer }}
                </h3>
                <button wire:click="closeTerminal" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="lp-modal-body" style="display:flex;flex-direction:column;">
                {{-- Output Area --}}
                <div style="flex:1;overflow-y:auto;background:rgba(0,0,0,0.6);border:1px solid var(--glass-border);border-radius:8px;padding:16px;margin-bottom:16px;max-height:50vh;" id="terminal-output-container">
                <pre style="margin:0;font-family:monospace;font-size:13px;color:#a8b2d1;white-space:pre-wrap;word-break:break-all;">{{ $terminalOutput }}</pre>
            </div>

            {{-- Input Area --}}
            <div style="display:flex;gap:12px;">
                <input type="text" wire:model.defer="terminalCommand" wire:keydown.enter="runTerminalCommand" class="form-input" style="margin:0;font-family:monospace;" placeholder="Escribe un comando y presiona Enter (ej. php artisan migrate, ls -la)..." autofocus>
                <button wire:click="runTerminalCommand" class="btn btn-primary" wire:loading.attr="disabled" wire:target="runTerminalCommand">
                    <span wire:loading.remove wire:target="runTerminalCommand"><i class="fa-solid fa-paper-plane"></i> Enviar</span>
                    <span wire:loading wire:target="runTerminalCommand"><i class="fa-solid fa-spinner fa-spin"></i> Ejecutando...</span>
                </button>
            </div>
            
            {{-- Auto-scroll script for terminal --}}
            <script>
                document.addEventListener('livewire:initialized', () => {
                    Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
                        succeed(({ snapshot, effect }) => {
                            setTimeout(() => {
                                let el = document.getElementById('terminal-output-container');
                                if(el) el.scrollTop = el.scrollHeight;
                            }, 50);
                        })
                    })
                });
            </script>
            </div>
        </div>
    </div>
    @endif
</div>
