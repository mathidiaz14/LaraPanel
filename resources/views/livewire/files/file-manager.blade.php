<div style="display:flex;height:calc(100vh - 140px);gap:20px;font-family:'Outfit', sans-serif;color:var(--text-primary);" 
     x-data="{ selectedAll: false, isUploading: false, progress: 0 }"
     x-on:livewire-upload-start="isUploading = true"
     x-on:livewire-upload-finish="isUploading = false"
     x-on:livewire-upload-error="isUploading = false"
     x-on:livewire-upload-progress="progress = $event.detail.progress">
    {{-- Left Sidebar: Tree & Shortcuts --}}
    <div class="glass" style="width:280px;display:flex;flex-direction:column;padding:0;border-right:1px solid var(--glass-border);background:rgba(10, 15, 30, 0.4);">
        <div style="padding:24px 20px;border-bottom:1px solid var(--glass-border);">
            <h3 style="font-size:16px;font-weight:700;margin:0;display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-hard-drive" style="color:var(--accent-light);font-size:18px;"></i> 
                <span>Explorador</span>
            </h3>
        </div>
        
        <div style="flex:1;overflow-y:auto;padding:16px 12px;display:flex;flex-direction:column;gap:20px;">
            {{-- Ubicaciones Rápidas --}}
            <div>
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1.5px;font-weight:800;margin:0 12px 10px;display:flex;align-items:center;justify-content:between;">
                    <span>Accesos Rápidos</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:4px;">
                    <button wire:click="navigate('')" class="btn btn-ghost" style="width:100%;text-align:left;justify-content:flex-start;padding:10px 14px;border-radius:8px;background:{{ $currentPath === '' ? 'rgba(99,102,241,0.15)' : 'transparent' }};color:{{ $currentPath === '' ? 'var(--accent-light)' : 'var(--text-secondary)' }};font-size:13px;font-weight:600;">
                        <i class="fa-solid fa-folder-tree" style="width:20px;font-size:14px;color:{{ $currentPath === '' ? 'var(--accent-light)' : 'var(--text-muted)' }};"></i> Raíz del Servidor
                    </button>
                    
                    <button wire:click="navigate('html')" class="btn btn-ghost" style="width:100%;text-align:left;justify-content:flex-start;padding:10px 14px;border-radius:8px;background:{{ $currentPath === 'html' ? 'rgba(99,102,241,0.15)' : 'transparent' }};color:{{ $currentPath === 'html' ? 'var(--accent-light)' : 'var(--text-secondary)' }};font-size:13px;font-weight:600;">
                        <i class="fa-solid fa-code" style="width:20px;font-size:14px;color:{{ $currentPath === 'html' ? 'var(--accent-light)' : 'var(--text-muted)' }};"></i> HTML Público
                    </button>
                </div>
            </div>

            {{-- Sitios / Dominios --}}
            <div>
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1.5px;font-weight:800;margin:0 12px 10px;">Sitios Web</div>
                <div style="display:flex;flex-direction:column;gap:4px;max-height:220px;overflow-y:auto;">
                    @php $hasDomains = false; @endphp
                    @foreach($items as $item)
                        @if($item['is_dir'] && $currentPath === '')
                            @php $hasDomains = true; @endphp
                            <button wire:key="domain-{{ md5($item['name']) }}" wire:click="navigate('{{ $item['name'] }}')" class="btn btn-ghost" style="width:100%;text-align:left;justify-content:flex-start;padding:8px 12px;font-size:13px;color:var(--text-secondary);border-radius:8px;">
                                <i class="fa-solid fa-globe" style="color:var(--accent-light);width:20px;font-size:14px;"></i> {{ $item['name'] }}
                            </button>
                        @endif
                    @endforeach
                    @if(!$hasDomains && $currentPath !== '')
                        <div style="font-size:12px;color:var(--text-muted);padding:8px 12px;font-style:italic;">
                            Navega a la raíz para ver sitios.
                        </div>
                    @endif
                </div>
            </div>
            
            {{-- Quick Stats (Disk Usage / RAM) --}}
            <div style="margin-top:auto;background:rgba(255,255,255,0.02);border:1px solid var(--glass-border);border-radius:12px;padding:16px;">
                <div style="font-size:12px;font-weight:700;color:var(--text-secondary);margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;">
                    <span>Almacenamiento VPS</span>
                    <i class="fa-solid fa-circle-info" style="color:var(--text-muted);" title="{{ \App\Services\MonitoringService::formatBytes($diskInfo['used'] ?? 0) }} usados de {{ \App\Services\MonitoringService::formatBytes($diskInfo['total'] ?? 0) }}"></i>
                </div>
                <div style="height:6px;background:rgba(255,255,255,0.1);border-radius:3px;overflow:hidden;margin-bottom:8px;">
                    <div style="height:100%;background:linear-gradient(90deg, var(--accent-light), #818cf8);width:{{ $diskInfo['usage'] ?? 0 }}%;border-radius:3px;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);">
                    <span>Uso aprox: {{ $diskInfo['usage'] ?? 0 }}%</span>
                    <span>Hetzner VPS</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Panel: Explorer & Actions --}}
    <div class="glass" style="flex:1;display:flex;flex-direction:column;padding:0;overflow:hidden;background:rgba(10, 15, 30, 0.2);">
        {{-- Top Toolbar --}}
        <div style="padding:16px 24px;border-bottom:1px solid var(--glass-border);display:flex;align-items:center;justify-content:space-between;gap:20px;background:rgba(255,255,255,0.01);">
            {{-- Breadcrumb path navigation --}}
            <div style="display:flex;align-items:center;gap:6px;font-family:monospace;font-size:14px;overflow-x:auto;white-space:nowrap;flex:1;">
                <button wire:click="navigateUp" class="btn btn-ghost btn-sm" style="padding:6px 10px;border-radius:6px;background:rgba(255,255,255,0.03);" @if($currentPath === '') disabled style="opacity:0.3;cursor:not-allowed;" @endif>
                    <i class="fa-solid fa-level-up-alt" style="transform:rotate(-90deg);"></i>
                </button>
                <span style="color:var(--glass-border);">|</span>
                <i class="fa-solid fa-folder-open" style="color:var(--accent-light);font-size:15px;margin-right:2px;"></i>
                <span wire:click="navigate('')" style="color:var(--accent-light);cursor:pointer;font-weight:700;font-family:'Outfit';">var/www</span>
                @foreach($breadcrumbs as $bc)
                    <span style="color:var(--text-muted);">/</span>
                    <span wire:click="navigate('{{ ltrim($bc['path'], '/') }}')" style="color:var(--text-primary);cursor:pointer;font-family:'Outfit';hover:color:var(--accent-light);">{{ $bc['name'] }}</span>
                @endforeach
            </div>

            {{-- New Item Actions --}}
            <div style="display:flex;align-items:center;gap:8px;">
                <button wire:click="$set('showCreateFolderModal', true)" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;background:rgba(255,255,255,0.03);">
                    <i class="fa-solid fa-folder-plus" style="color:var(--accent-light);"></i> 
                    <span>Nueva Carpeta</span>
                </button>
                <button wire:click="$set('showCreateFileModal', true)" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;background:rgba(255,255,255,0.03);">
                    <i class="fa-solid fa-file-plus" style="color:var(--accent-light);"></i> 
                    <span>Nuevo Archivo</span>
                </button>
                <label class="btn btn-primary btn-sm" style="cursor:pointer;margin:0;display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:8px;background:var(--accent-light);border:none;color:black;font-weight:700;transition:transform 0.2s;">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Subir Archivo</span>
                    <input type="file" wire:model.live="uploads" multiple style="display:none;">
                </label>
            </div>
        </div>

        {{-- Alerts --}}
        @if($successMessage)
            <div style="padding:12px 24px;background:rgba(34,197,94,0.12);color:#4ade80;font-size:13px;border-bottom:1px solid rgba(34,197,94,0.2);display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-circle-check" style="font-size:16px;"></i> 
                <span>{{ $successMessage }}</span>
            </div>
        @endif
        @if($errorMessage)
            <div style="padding:12px 24px;background:rgba(239,68,68,0.12);color:#f87171;font-size:13px;border-bottom:1px solid rgba(239,68,68,0.2);display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-circle-exclamation" style="font-size:16px;"></i> 
                <span>{{ $errorMessage }}</span>
            </div>
        @endif

        {{-- File List Container --}}
        <div style="flex:1;overflow-y:auto;padding:0;position:relative;">
            <table class="table" style="width:100%;margin:0;border-collapse:collapse;font-size:13px;">
                <thead style="position:sticky;top:0;background:rgba(15, 23, 42, 0.95);backdrop-filter:blur(12px);z-index:10;border-bottom:1px solid var(--glass-border);">
                    <tr>
                        <th style="padding:14px 20px;width:4%;text-align:center;">
                            <input type="checkbox" x-model="selectedAll" @change="
                                $el.checked ? 
                                @this.set('selectedItems', Array.from(document.querySelectorAll('.file-checkbox')).map(el => el.value)) : 
                                @this.set('selectedItems', [])
                            " style="width:16px;height:16px;accent-color:var(--accent-light);cursor:pointer;border-radius:4px;">
                        </th>
                        <th style="padding:14px 10px;width:38%;text-align:left;font-weight:700;color:var(--text-muted);">Nombre</th>
                        <th style="width:10%;text-align:left;font-weight:700;color:var(--text-muted);">Tamaño</th>
                        <th style="width:14%;text-align:left;font-weight:700;color:var(--text-muted);">Usuario/Grupo</th>
                        <th style="width:10%;text-align:left;font-weight:700;color:var(--text-muted);">Permisos</th>
                        <th style="width:12%;text-align:left;font-weight:700;color:var(--text-muted);">Modificado</th>
                        <th style="text-align:right;padding-right:24px;width:12%;font-weight:700;color:var(--text-muted);">Acciones</th>
                    </tr>
                </thead>
                <tbody style="background:transparent;">
                    @if(empty($items))
                    <tr>
                        <td colspan="7" style="text-align:center;padding:80px 20px;color:var(--text-muted);">
                            <div style="display:flex;flex-direction:column;align-items:center;gap:16px;">
                                <div style="width:64px;height:64px;background:rgba(255,255,255,0.02);border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid var(--glass-border);">
                                    <i class="fa-regular fa-folder-open" style="font-size:28px;color:var(--text-muted);opacity:0.6;"></i>
                                </div>
                                <span style="font-size:14px;font-weight:600;">Este directorio está vacío</span>
                            </div>
                        </td>
                    </tr>
                    @endif

                    @foreach($items as $item)
                    @php
                        $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                        $isKnownText = in_array($ext, ['php', 'js', 'css', 'html', 'htm', 'txt', 'json', 'md', 'env', 'ini', 'conf', 'yaml', 'yml', 'sh', 'htaccess', '']);
                        $isBinary = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'zip', 'tar', 'gz', 'rar', 'pdf', 'mp3', 'mp4', 'avi', 'mov', 'ttf', 'woff', 'woff2', 'eot', 'sqlite', 'sqlite3']);
                    @endphp
                    <tr wire:key="file-row-{{ md5($item['name'] . '-' . $item['updated_at']) }}" 
                        style="border-bottom:1px solid rgba(255,255,255,0.03);transition:background 0.2s;background:{{ in_array($item['name'], $selectedItems) ? 'rgba(99, 102, 241, 0.05)' : 'transparent' }};cursor:default;" 
                        class="file-row"
                        @if($item['is_dir'])
                            wire:dblclick="navigate('{{ ltrim($currentPath . '/' . $item['name'], '/') }}')"
                        @elseif(!$isBinary)
                            @if(!$isKnownText)
                                x-on:dblclick="if(confirm('Este archivo tiene una extensión desconocida. ¿Intentar abrir como texto plano?')) { @this.editFile('{{ $item['name'] }}') }"
                            @else
                                wire:dblclick="editFile('{{ $item['name'] }}')"
                            @endif
                        @endif
                    >
                        <td style="padding:12px 20px;text-align:center;vertical-align:middle;">
                            <input type="checkbox" value="{{ $item['name'] }}" wire:model.live="selectedItems" class="file-checkbox" style="width:16px;height:16px;accent-color:var(--accent-light);cursor:pointer;border-radius:4px;">
                        </td>
                        <td style="padding:12px 10px;vertical-align:middle;">
                            @if($item['is_dir'])
                                <div wire:click="navigate('{{ ltrim($currentPath . '/' . $item['name'], '/') }}')" style="cursor:pointer;display:flex;align-items:center;gap:12px;color:var(--text-primary);font-weight:600;transition:color 0.2s;" onmouseover="this.style.color='var(--accent-light)'" onmouseout="this.style.color='var(--text-primary)'">
                                    <i class="fa-solid fa-folder" style="font-size:18px;color:#38bdf8;"></i>
                                    <span>{{ $item['name'] }}</span>
                                </div>
                            @else
                                <div style="display:flex;align-items:center;gap:12px;color:var(--text-primary);">
                                    @php
                                        $icon = 'fa-file-lines';
                                        $color = 'var(--text-secondary)';
                                        if (in_array($ext, ['php', 'js', 'css', 'html', 'json', 'yaml', 'yml', 'xml'])) { $icon = 'fa-file-code'; $color = '#a78bfa'; }
                                        elseif (in_array($ext, ['jpg', 'png', 'gif', 'svg', 'webp', 'ico'])) { $icon = 'fa-file-image'; $color = '#34d399'; }
                                        elseif (in_array($ext, ['zip', 'tar', 'gz', 'rar'])) { $icon = 'fa-file-zipper'; $color = '#fbbf24'; }
                                        elseif ($ext === 'env') { $icon = 'fa-lock-open'; $color = '#f87171'; }
                                    @endphp
                                    <i class="fa-solid {{ $icon }}" style="font-size:18px;color:{{ $color }};"></i>
                                    <span style="font-weight:500;">{{ $item['name'] }}</span>
                                </div>
                            @endif
                        </td>
                        <td style="color:var(--text-secondary);vertical-align:middle;">
                            @if($item['is_dir'])
                                <span style="color:var(--text-muted);font-family:monospace;font-size:11px;">DIR</span>
                            @else
                                @php
                                    $bytes = $item['size'];
                                    $units = ['B', 'KB', 'MB', 'GB'];
                                    for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) { $bytes /= 1024; }
                                    $sizeFormatted = round($bytes, 1) . ' ' . $units[$i];
                                @endphp
                                <span style="font-family:monospace;">{{ $sizeFormatted }}</span>
                            @endif
                        </td>
                        <td style="color:var(--text-secondary);vertical-align:middle;font-family:monospace;font-size:12px;">
                            <span style="color:#818cf8;">{{ $item['owner'] }}</span><span style="color:var(--text-muted);">:</span><span style="color:#a78bfa;">{{ $item['group'] }}</span>
                        </td>
                        <td style="vertical-align:middle;">
                            <button wire:click="openChmodModal('{{ $item['name'] }}', '{{ $item['permissions'] }}')" style="background:rgba(255,255,255,0.03);border:1px solid var(--glass-border);color:var(--text-muted);border-radius:6px;padding:3px 8px;font-family:monospace;font-size:11px;cursor:pointer;transition:all 0.2s;" onmouseover="this.style.borderColor='var(--accent-light)'" onmouseout="this.style.borderColor='var(--glass-border)'">
                                <i class="fa-solid fa-shield-halved" style="font-size:10px;margin-right:4px;"></i>{{ $item['permissions'] }}
                            </button>
                        </td>
                        <td style="color:var(--text-secondary);vertical-align:middle;font-size:12px;">
                            {{ date('d M H:i', $item['updated_at']) }}
                        </td>
                        <td style="text-align:right;padding-right:24px;vertical-align:middle;">
                            <div style="display:inline-flex;gap:4px;">
                                @if(!$item['is_dir'])
                                    @if(!$isBinary)
                                    <button 
                                        @if(!$isKnownText)
                                            x-on:click="if(confirm('Este archivo tiene una extensión desconocida. ¿Intentar abrir como texto plano?')) { @this.editFile('{{ $item['name'] }}') }"
                                        @else
                                            wire:click="editFile('{{ $item['name'] }}')"
                                        @endif
                                        class="btn btn-ghost btn-sm" title="Editar código" style="padding:6px 10px;border-radius:6px;background:rgba(99,102,241,0.08);">
                                        <i class="fa-solid fa-code" style="color:var(--accent-light);font-size:14px;"></i>
                                    </button>
                                    @endif
                                    <button wire:click="downloadItem('{{ $item['name'] }}')" class="btn btn-ghost btn-sm" title="Descargar" style="padding:6px 10px;border-radius:6px;">
                                        <i class="fa-solid fa-download" style="font-size:14px;"></i>
                                    </button>
                                @endif

                                @if($item['is_dir'])
                                    <button wire:click="$set('newFolderName', '{{ $item['name'] }}.zip'); @this.set('selectedItems', ['{{ $item['name'] }}']); zipSelected();" class="btn btn-ghost btn-sm" title="Comprimir Zip" style="padding:6px 10px;border-radius:6px;">
                                        <i class="fa-solid fa-file-zipper" style="color:var(--warning);font-size:14px;"></i>
                                    </button>
                                @elseif(in_array(strtolower(pathinfo($item['name'], PATHINFO_EXTENSION)), ['zip', 'tar', 'gz']))
                                    <button wire:click="unzipItem('{{ $item['name'] }}')" class="btn btn-ghost btn-sm" title="Extraer Aquí" style="padding:6px 10px;border-radius:6px;">
                                        <i class="fa-solid fa-box-open" style="color:var(--success);font-size:14px;"></i>
                                    </button>
                                @endif

                                <button wire:click="openRenameModal('{{ $item['name'] }}')" class="btn btn-ghost btn-sm" title="Renombrar" style="padding:6px 10px;border-radius:6px;">
                                    <i class="fa-solid fa-pen-to-square" style="font-size:14px;"></i>
                                </button>
                                <button wire:click="deleteItem('{{ $item['name'] }}')" class="btn btn-ghost btn-sm" title="Eliminar" onclick="return confirm('¿Seguro que desea eliminar {{ $item['name'] }} de forma permanente?')" style="padding:6px 10px;border-radius:6px;color:var(--danger);">
                                    <i class="fa-solid fa-trash-can" style="font-size:14px;"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Floating Action Bar for Selected Items (Bulk Actions) --}}
        @if(!empty($selectedItems))
        <div style="position:absolute;bottom:20px;left:50%;transform:translateX(-50%);z-index:90;display:flex;align-items:center;gap:16px;background:rgba(15, 23, 42, 0.95);border:1px solid var(--accent-light);box-shadow:0 10px 30px rgba(0,0,0,0.5);border-radius:14px;padding:12px 24px;backdrop-filter:blur(16px);animation:slideUp 0.3s ease-out;">
            <div style="font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;">
                <span style="width:20px;height:20px;border-radius:50%;background:var(--accent-light);color:black;display:flex;align-items:center;justify-content:center;font-size:11px;">{{ count($selectedItems) }}</span>
                <span style="color:var(--text-primary);">seleccionados</span>
            </div>
            
            <div style="width:1px;height:24px;background:var(--glass-border);"></div>
            
            <div style="display:flex;gap:8px;">
                <button wire:click="$set('showCreateFolderModal', true); @this.set('newFolderName', 'archivo_comprimido.zip')" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px;background:rgba(251,191,36,0.1);color:var(--warning);border-radius:8px;padding:6px 12px;font-size:12px;">
                    <i class="fa-solid fa-file-zipper"></i> Comprimir
                </button>
                <button wire:click="$set('showBulkMoveModal', true); @this.set('bulkDestDirectory', '')" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px;background:rgba(99,102,241,0.1);color:#a5b4fc;border-radius:8px;padding:6px 12px;font-size:12px;">
                    <i class="fa-solid fa-arrows-up-down-left-right"></i> Mover
                </button>
                <button wire:click="$set('showBulkCopyModal', true); @this.set('bulkDestDirectory', '')" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px;background:rgba(16,185,129,0.1);color:#6ee7b7;border-radius:8px;padding:6px 12px;font-size:12px;">
                    <i class="fa-solid fa-clone"></i> Copiar
                </button>
                <button wire:click="deleteSelected" onclick="return confirm('¿Seguro que deseas eliminar los {{ count($selectedItems) }} elementos seleccionados?')" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px;background:rgba(239,68,68,0.1);color:#f87171;border-radius:8px;padding:6px 12px;font-size:12px;">
                    <i class="fa-solid fa-trash-can"></i> Eliminar
                </button>
            </div>
            
            <div style="width:1px;height:24px;background:var(--glass-border);"></div>
            
            <button wire:click="$set('selectedItems', [])" class="btn btn-ghost btn-sm" style="color:var(--text-muted);font-size:12px;padding:6px 10px;">
                Deseleccionar todo
            </button>
        </div>
        @endif
    </div>

    {{-- Modals (Create Folder, Create File, Chmod, Rename, Bulk Move, Bulk Copy) --}}
    
    {{-- Create Folder Modal --}}
    @if($showCreateFolderModal)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.8);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="width:100%;max-width:380px;padding:28px;border-radius:16px;border:1px solid var(--glass-border);background:rgba(15,23,42,0.95);">
            @if(!empty($selectedItems) && str_ends_with($newFolderName, '.zip'))
                <h3 style="font-size:18px;font-weight:700;margin:0 0 16px;display:flex;align-items:center;gap:10px;">
                    <i class="fa-solid fa-file-zipper" style="color:var(--warning);"></i> 
                    <span>Comprimir Selección</span>
                </h3>
                <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:8px;">Nombre del archivo Zip:</label>
                <input type="text" wire:model="newFolderName" class="form-input" placeholder="ej. backup.zip" autofocus style="width:100%;padding:10px;background:rgba(0,0,0,0.2);border:1px solid var(--glass-border);border-radius:8px;color:white;">
                @error('newFolderName') <div style="font-size:11px;color:#f87171;margin-top:6px;">{{ $message }}</div> @enderror
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:24px;">
                    <button wire:click="$set('showCreateFolderModal', false); @this.set('newFolderName', '')" class="btn btn-ghost btn-sm" style="border-radius:8px;padding:8px 16px;">Cancelar</button>
                    <button wire:click="zipSelected" class="btn btn-primary btn-sm" style="border-radius:8px;padding:8px 18px;background:var(--accent-light);color:black;border:none;font-weight:700;">Comprimir</button>
                </div>
            @else
                <h3 style="font-size:18px;font-weight:700;margin:0 0 16px;display:flex;align-items:center;gap:10px;">
                    <i class="fa-solid fa-folder-plus" style="color:var(--accent-light);"></i> 
                    <span>Nueva Carpeta</span>
                </h3>
                <input type="text" wire:model="newFolderName" class="form-input" placeholder="Escribe el nombre de la carpeta..." autofocus style="width:100%;padding:10px;background:rgba(0,0,0,0.2);border:1px solid var(--glass-border);border-radius:8px;color:white;">
                @error('newFolderName') <div style="font-size:11px;color:#f87171;margin-top:6px;">{{ $message }}</div> @enderror
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:24px;">
                    <button wire:click="$set('showCreateFolderModal', false)" class="btn btn-ghost btn-sm" style="border-radius:8px;padding:8px 16px;">Cancelar</button>
                    <button wire:click="createFolder" class="btn btn-primary btn-sm" style="border-radius:8px;padding:8px 18px;background:var(--accent-light);color:black;border:none;font-weight:700;">Crear Carpeta</button>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Create File Modal --}}
    @if($showCreateFileModal)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.8);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="width:100%;max-width:380px;padding:28px;border-radius:16px;border:1px solid var(--glass-border);background:rgba(15,23,42,0.95);">
            <h3 style="font-size:18px;font-weight:700;margin:0 0 16px;display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-file-circle-plus" style="color:var(--accent-light);"></i> 
                <span>Nuevo Archivo</span>
            </h3>
            <input type="text" wire:model="newFileName" class="form-input" placeholder="ej. index.php" autofocus style="width:100%;padding:10px;background:rgba(0,0,0,0.2);border:1px solid var(--glass-border);border-radius:8px;color:white;">
            @error('newFileName') <div style="font-size:11px;color:#f87171;margin-top:6px;">{{ $message }}</div> @enderror
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:24px;">
                <button wire:click="$set('showCreateFileModal', false)" class="btn btn-ghost btn-sm" style="border-radius:8px;padding:8px 16px;">Cancelar</button>
                <button wire:click="createFile" class="btn btn-primary btn-sm" style="border-radius:8px;padding:8px 18px;background:var(--accent-light);color:black;border:none;font-weight:700;">Crear Archivo</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Chmod Permissions Modal --}}
    @if($chmodPath)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.8);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="width:100%;max-width:380px;padding:28px;border-radius:16px;border:1px solid var(--glass-border);background:rgba(15,23,42,0.95);">
            <h3 style="font-size:18px;font-weight:700;margin:0 0 16px;display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-shield-halved" style="color:var(--accent-light);"></i> 
                <span>Cambiar Permisos (Chmod)</span>
            </h3>
            <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:8px;">Modo octal:</label>
            <input type="text" wire:model="chmodOctal" class="form-input" placeholder="0755" autofocus style="width:100%;padding:10px;background:rgba(0,0,0,0.2);border:1px solid var(--glass-border);border-radius:8px;color:white;font-family:monospace;letter-spacing:2px;font-size:16px;text-align:center;">
            @error('chmodOctal') <div style="font-size:11px;color:#f87171;margin-top:6px;">{{ $message }}</div> @enderror
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:24px;">
                <button wire:click="$set('chmodPath', null)" class="btn btn-ghost btn-sm" style="border-radius:8px;padding:8px 16px;">Cancelar</button>
                <button wire:click="saveChmod" class="btn btn-primary btn-sm" style="border-radius:8px;padding:8px 18px;background:var(--accent-light);color:black;border:none;font-weight:700;">Guardar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Rename Modal --}}
    @if($renamingPath)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.8);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="width:100%;max-width:380px;padding:28px;border-radius:16px;border:1px solid var(--glass-border);background:rgba(15,23,42,0.95);">
            <h3 style="font-size:18px;font-weight:700;margin:0 0 16px;display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-pen-nib" style="color:var(--accent-light);"></i> 
                <span>Renombrar Recurso</span>
            </h3>
            <input type="text" wire:model="newName" class="form-input" placeholder="Nuevo nombre..." autofocus style="width:100%;padding:10px;background:rgba(0,0,0,0.2);border:1px solid var(--glass-border);border-radius:8px;color:white;">
            @error('newName') <div style="font-size:11px;color:#f87171;margin-top:6px;">{{ $message }}</div> @enderror
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:24px;">
                <button wire:click="$set('renamingPath', null)" class="btn btn-ghost btn-sm" style="border-radius:8px;padding:8px 16px;">Cancelar</button>
                <button wire:click="renameItem" class="btn btn-primary btn-sm" style="border-radius:8px;padding:8px 18px;background:var(--accent-light);color:black;border:none;font-weight:700;">Renombrar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Bulk Move Modal --}}
    @if($showBulkMoveModal)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.8);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="width:100%;max-width:420px;padding:28px;border-radius:16px;border:1px solid var(--glass-border);background:rgba(15,23,42,0.95);">
            <h3 style="font-size:18px;font-weight:700;margin:0 0 16px;display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-arrows-up-down-left-right" style="color:var(--accent-light);"></i> 
                <span>Mover a la carpeta</span>
            </h3>
            <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:8px;">Directorio de destino (ruta relativa a la raíz web):</label>
            <input type="text" wire:model="bulkDestDirectory" class="form-input" placeholder="ej. html/tienda (vacío para la raíz)" autofocus style="width:100%;padding:10px;background:rgba(0,0,0,0.2);border:1px solid var(--glass-border);border-radius:8px;color:white;font-family:monospace;">
            @error('bulkDestDirectory') <div style="font-size:11px;color:#f87171;margin-top:6px;">{{ $message }}</div> @enderror
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:24px;">
                <button wire:click="$set('showBulkMoveModal', false)" class="btn btn-ghost btn-sm" style="border-radius:8px;padding:8px 16px;">Cancelar</button>
                <button wire:click="moveSelected" class="btn btn-primary btn-sm" style="border-radius:8px;padding:8px 18px;background:var(--accent-light);color:black;border:none;font-weight:700;">Mover Elementos</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Bulk Copy Modal --}}
    @if($showBulkCopyModal)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.8);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="width:100%;max-width:420px;padding:28px;border-radius:16px;border:1px solid var(--glass-border);background:rgba(15,23,42,0.95);">
            <h3 style="font-size:18px;font-weight:700;margin:0 0 16px;display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-clone" style="color:var(--accent-light);"></i> 
                <span>Copiar a la carpeta</span>
            </h3>
            <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:8px;">Directorio de destino (ruta relativa a la raíz web):</label>
            <input type="text" wire:model="bulkDestDirectory" class="form-input" placeholder="ej. html/copias (vacío para la raíz)" autofocus style="width:100%;padding:10px;background:rgba(0,0,0,0.2);border:1px solid var(--glass-border);border-radius:8px;color:white;font-family:monospace;">
            @error('bulkDestDirectory') <div style="font-size:11px;color:#f87171;margin-top:6px;">{{ $message }}</div> @enderror
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:24px;">
                <button wire:click="$set('showBulkCopyModal', false)" class="btn btn-ghost btn-sm" style="border-radius:8px;padding:8px 16px;">Cancelar</button>
                <button wire:click="copySelected" class="btn btn-primary btn-sm" style="border-radius:8px;padding:8px 18px;background:var(--accent-light);color:black;border:none;font-weight:700;">Copiar Elementos</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Advanced Monaco Editor Overlay --}}
    @if($editingPath)
    <div style="position:fixed;inset:0;z-index:300;background:rgba(8,11,20,0.99);display:flex;flex-direction:column;backdrop-filter:blur(12px);" id="monaco-full-editor">
        {{-- Editor Header --}}
        <div style="background:rgba(255,255,255,0.02);padding:14px 28px;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:16px;">
                <div style="width:40px;height:40px;background:rgba(99,102,241,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;border:1px solid rgba(99,102,241,0.25);">
                    <i class="fa-solid fa-code" style="color:var(--accent-light);font-size:18px;"></i>
                </div>
                <div>
                    <strong style="font-size:15px;color:var(--text-primary);font-family:monospace;letter-spacing:0.5px;">{{ basename($editingPath) }}</strong>
                    <div style="font-size:11px;color:var(--text-muted);font-family:monospace;margin-top:2px;">/var/www/{{ ltrim($editingPath, '/') }}</div>
                </div>
            </div>
            
            {{-- Editor Status & Actions --}}
            <div style="display:flex;align-items:center;gap:18px;">
                {{-- Save status indicator --}}
                <div id="editor-save-status" style="font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-circle-check" style="color:#22c55e;"></i>
                    <span>Listo</span>
                </div>

                {{-- Language selector --}}
                <select id="editor-language-select" onchange="changeEditorLanguage(this.value)" style="background:rgba(0,0,0,0.4);border:1px solid var(--glass-border);color:white;border-radius:6px;padding:6px 12px;font-size:12px;outline:none;cursor:pointer;">
                    <option value="plaintext">Texto Plano</option>
                    <option value="php">PHP</option>
                    <option value="javascript">JavaScript</option>
                    <option value="html">HTML</option>
                    <option value="css">CSS</option>
                    <option value="json">JSON</option>
                    <option value="markdown">Markdown</option>
                    <option value="shell">Shell Script</option>
                </select>

                <button onclick="saveMonacoContent()" class="btn btn-primary" style="height:36px;background:var(--accent-light);border:none;color:black;font-weight:700;border-radius:8px;padding:0 18px;display:flex;align-items:center;gap:8px;cursor:pointer;transition:transform 0.1s;" onmousedown="this.style.transform='scale(0.97)'" onmouseup="this.style.transform='scale(1)'">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar <span style="font-size:10px;opacity:0.6;background:rgba(0,0,0,0.15);padding:2px 6px;border-radius:4px;margin-left:4px;font-family:monospace;">Ctrl+S</span>
                </button>
                
                <button wire:click="$set('editingPath', null)" class="btn btn-ghost" style="height:36px;background:rgba(255,255,255,0.05);border-radius:8px;padding:0 16px;color:white;border:1px solid var(--glass-border);">
                    Cerrar
                </button>
            </div>
        </div>

        {{-- Monaco Editor Container --}}
        <div style="flex:1;position:relative;background:#181818;">
            <iframe id="monaco-editor-iframe" src="/monaco-editor-frame.html" style="position:absolute;inset:0;width:100%;height:100%;border:none;"></iframe>
        </div>
    </div>
    @endif

    {{-- Monaco Initialization & Ctrl+S --}}
    <script>
        let selectedLang = 'plaintext';
        let editorIframeReady = false;
        let pendingEditorContent = null;
        let pendingEditorLang = null;

        // Escuchar mensajes del iframe
        window.addEventListener('message', function(event) {
            const data = event.data;
            if (data.action === 'ready') {
                editorIframeReady = true;
                if (pendingEditorContent !== null) {
                    sendToIframe(pendingEditorContent, pendingEditorLang);
                }
            } else if (data.action === 'save') {
                saveMonacoContent(data.content);
            }
        });

        window.addEventListener('open-editor', event => {
            const content = event.detail.content;
            const filename = event.detail.filename;
            let lang = 'plaintext';
            const ext = filename.split('.').pop().toLowerCase();
            
            if (ext === 'js') lang = 'javascript';
            else if (ext === 'html' || ext === 'htm') lang = 'html';
            else if (ext === 'css') lang = 'css';
            else if (ext === 'php') lang = 'php';
            else if (ext === 'json') lang = 'json';
            else if (ext === 'md') lang = 'markdown';
            else if (ext === 'sh') lang = 'shell';
            else if (ext === 'yaml' || ext === 'yml') lang = 'yaml';
            else if (ext === 'xml') lang = 'xml';

            selectedLang = lang;

            // Livewire destróy y recrea el iframe, por lo que el nuevo iframe aún no está listo.
            editorIframeReady = false;
            pendingEditorContent = content;
            pendingEditorLang = lang;

            // Establecer valor del selector de lenguaje
            setTimeout(() => {
                const select = document.getElementById('editor-language-select');
                if (select) select.value = lang;
            }, 100);
        });

        function sendToIframe(content, language) {
            const iframe = document.getElementById('monaco-editor-iframe');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.postMessage({
                    action: 'init',
                    content: content,
                    language: language
                }, '*');
                pendingEditorContent = null;
                pendingEditorLang = null;
            }
        }

        function changeEditorLanguage(lang) {
            selectedLang = lang;
            const iframe = document.getElementById('monaco-editor-iframe');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.postMessage({
                    action: 'changeLanguage',
                    language: lang
                }, '*');
            }
        }

        function saveMonacoContent(content = null) {
            const statusDiv = document.getElementById('editor-save-status');
            statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin" style="color:var(--warning);"></i> <span style="color:var(--warning);">Guardando...</span>`;

            if (content !== null) {
                executeSave(content);
            } else {
                // Solicitar valor al iframe
                const iframe = document.getElementById('monaco-editor-iframe');
                if (iframe && iframe.contentWindow) {
                    const onValueReceived = function(e) {
                        if (e.data && e.data.action === 'value') {
                            window.removeEventListener('message', onValueReceived);
                            executeSave(e.data.content);
                        }
                    };
                    window.addEventListener('message', onValueReceived);
                    iframe.contentWindow.postMessage({ action: 'getValue' }, '*');
                }
            }
        }

        function executeSave(value) {
            const statusDiv = document.getElementById('editor-save-status');
            @this.call('saveFileContent', value).then(() => {
                statusDiv.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#22c55e;"></i> <span style="color:#22c55e;">Guardado con éxito</span>`;
                setTimeout(() => {
                    statusDiv.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#22c55e;"></i> <span style="color:var(--text-muted);">Listo</span>`;
                }, 2000);
            }).catch(err => {
                statusDiv.innerHTML = `<i class="fa-solid fa-circle-xmark" style="color:#ef4444;"></i> <span style="color:#ef4444;">Fallo al guardar</span>`;
            });
        }

        // Global keydown handler (backup)
        window.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                const modal = document.getElementById('monaco-full-editor');
                if (modal) {
                    e.preventDefault();
                    saveMonacoContent();
                }
            }
        });
    </script>

    {{-- Upload Progress Modal --}}
    <div x-show="isUploading" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);backdrop-filter:blur(5px);z-index:9999;align-items:center;justify-content:center;" x-bind:style="isUploading ? 'display:flex;' : 'display:none;'">
        <div style="background:rgba(15, 23, 42, 0.95);border:1px solid var(--glass-border);border-radius:12px;padding:32px;width:100%;max-width:400px;text-align:center;">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size:48px;color:var(--accent-light);margin-bottom:16px;"></i>
            <h3 style="font-size:18px;font-weight:700;margin-bottom:12px;">Subiendo Archivos...</h3>
            <div style="width:100%;height:8px;background:rgba(255,255,255,0.1);border-radius:4px;overflow:hidden;margin-bottom:12px;">
                <div style="height:100%;background:var(--accent-light);border-radius:4px;transition:width 0.3s;" :style="`width: ${progress}%`"></div>
            </div>
            <div style="font-size:14px;font-weight:600;color:var(--text-secondary);"><span x-text="progress"></span>% Completado</div>
            <p style="font-size:12px;color:var(--text-muted);margin-top:12px;">Por favor espera, procesando en segundo plano...</p>
        </div>
    </div>
</div>
<style>
@keyframes slideUp {
    from { transform: translate(-50%, 50px); opacity: 0; }
    to { transform: translate(-50%, 0); opacity: 1; }
}
.file-row:hover {
    background: rgba(255, 255, 255, 0.02) !important;
}
.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}
.form-input {
    border: 1px solid var(--glass-border);
    background: rgba(0,0,0,0.2);
    border-radius: 8px;
    padding: 10px;
    color: white;
    width: 100%;
}
</style>
