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
    </div>

    @if(!$daemonRunning)
        <div class="glass" style="padding:40px;text-align:center;">
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
                <div class="glass" style="padding:16px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                        <h2 style="font-size:14px;font-weight:700;color:var(--text-primary);">Contenedores</h2>
                        <button wire:click="loadContainers" class="btn btn-ghost btn-sm" title="Refrescar Lista">
                            <i class="fa-solid fa-rotate"></i>
                        </button>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:8px;max-height:600px;overflow-y:auto;padding-right:4px;">
                        @foreach($containers as $c)
                            @php
                                $isRunning = str_contains(strtolower($c['state']), 'running');
                                $isExited = str_contains(strtolower($c['state']), 'exited');
                                $isPaused = str_contains(strtolower($c['state']), 'paused');
                                $borderColor = ($selectedContainer && $selectedContainer['name'] === $c['name']) ? 'rgba(99,102,241,0.5)' : 'var(--glass-border)';
                                $bgColor = ($selectedContainer && $selectedContainer['name'] === $c['name']) ? 'rgba(99,102,241,0.1)' : 'rgba(255,255,255,0.02)';
                            @endphp
                            <button wire:click="selectContainer('{{ $c['name'] }}')" 
                                style="width:100%;text-align:left;padding:12px;border-radius:8px;border:1px solid {{ $borderColor }};background:{{ $bgColor }};cursor:pointer;transition:all 0.2s;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                                    <span style="font-size:13px;font-weight:600;color:var(--text-primary);text-overflow:ellipsis;white-space:nowrap;overflow:hidden;max-width:180px;">
                                        {{ $c['name'] }}
                                    </span>
                                    <span class="badge {{ $isRunning ? 'badge-success' : ($isPaused ? 'badge-warning' : 'badge-secondary') }}" style="font-size:9px;padding:2px 6px;">
                                        {{ $c['state'] }}
                                    </span>
                                </div>
                                <div style="font-size:11px;color:var(--text-muted);text-overflow:ellipsis;white-space:nowrap;overflow:hidden;margin-bottom:2px;">
                                    {{ $c['image'] }}
                                </div>
                                <div style="font-size:10px;color:var(--text-muted);">
                                    {{ $c['ports'] ?: 'Sin puertos expuestos' }}
                                </div>
                            </button>
                        @endforeach

                        @if(empty($containers))
                            <div style="text-align:center;padding:20px 10px;color:var(--text-muted);font-size:12px;">
                                No hay contenedores creados.
                            </div>
                        @endif
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
                        <div class="glass" style="padding:24px;margin-bottom:20px;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                                <div>
                                    <h2 style="font-size:20px;font-weight:700;color:var(--text-primary);margin-bottom:4px;">
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
                            <div class="glass" style="padding:20px;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                    <h3 style="font-size:14px;font-weight:700;color:var(--text-primary);"><i class="fa-solid fa-terminal"></i> Terminal Logs</h3>
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
                        <div class="glass" style="padding:80px 20px;text-align:center;">
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
                <div class="glass" style="padding:24px;">
                    <h3 style="font-size:15px;font-weight:700;margin-bottom:16px;color:var(--text-primary);"><i class="fa-solid fa-cloud-arrow-down"></i> Descargar Imagen (Pull Image)</h3>
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
                <div class="glass" style="padding:20px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <h3 style="font-size:15px;font-weight:700;color:var(--text-primary);"><i class="fa-solid fa-compact-disc"></i> Imágenes Locales</h3>
                        <button wire:click="pruneImages" class="btn btn-ghost btn-sm" onclick="return confirm('¿Eliminar todas las imágenes sin usar para liberar espacio?')">
                            <i class="fa-solid fa-broom"></i> Limpiar no usadas (Prune)
                        </button>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="table" style="width:100%;">
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
                <div class="glass" style="padding:24px;">
                    <h3 style="font-size:15px;font-weight:700;margin-bottom:16px;color:var(--text-primary);"><i class="fa-solid fa-cube"></i> Docker Compose Stacks</h3>
                    
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
                    <div class="glass" style="padding:20px;">
                        <h4 style="font-size:13px;font-weight:700;margin-bottom:12px;color:var(--text-primary);"><i class="fa-solid fa-terminal"></i> Salida de Comandos</h4>
                        <pre style="background:rgba(0,0,0,0.8);border:1px solid var(--glass-border);border-radius:8px;padding:16px;font-family:monospace;font-size:11px;color:#cdd6f4;line-height:1.6;white-space:pre-wrap;max-height:300px;overflow-y:auto;text-align:left;">{{ $composeOutput }}</pre>
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
