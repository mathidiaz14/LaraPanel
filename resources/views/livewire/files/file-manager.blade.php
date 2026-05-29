<div style="display:flex;height:calc(100vh - 140px);gap:20px;">
    {{-- Left Sidebar: Shortcuts & Tree --}}
    <div class="glass" style="width:260px;display:flex;flex-direction:column;padding:0;">
        <div style="padding:20px;border-bottom:1px solid var(--glass-border);">
            <h3 style="font-size:16px;font-weight:700;"><i class="fa-solid fa-hard-drive" style="color:var(--accent-light);margin-right:8px;"></i> Almacenamiento</h3>
        </div>
        
        <div style="flex:1;overflow-y:auto;padding:10px;">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:700;margin:10px 10px 8px;">Ubicaciones</div>
            
            <button wire:click="navigate('')" class="btn btn-ghost" style="width:100%;text-align:left;justify-content:flex-start;background:{{ $currentPath === '' ? 'rgba(99,102,241,0.1)' : 'transparent' }};color:{{ $currentPath === '' ? 'var(--text-primary)' : 'var(--text-secondary)' }};">
                <i class="fa-solid fa-house" style="color:{{ $currentPath === '' ? 'var(--accent-light)' : 'var(--text-muted)' }};width:20px;"></i> Raíz del Usuario
            </button>
            
            <div style="margin-top:20px;border-top:1px solid var(--glass-border);padding-top:10px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:700;margin:10px 10px 8px;">Mis Dominios</div>
                {{-- In a real app we'd list domains here, for now we assume they are folders in root --}}
                @foreach($items as $item)
                    @if($item['is_dir'] && $currentPath === '')
                        <button wire:click="navigate('{{ $item['name'] }}')" class="btn btn-ghost" style="width:100%;text-align:left;justify-content:flex-start;padding:6px 12px;font-size:13px;color:var(--text-secondary);">
                            <i class="fa-solid fa-globe" style="color:var(--text-muted);width:20px;"></i> {{ $item['name'] }}
                        </button>
                    @endif
                @endforeach
            </div>
        </div>
        
        <div style="padding:20px;border-top:1px solid var(--glass-border);">
            {{-- Upload Progress Info --}}
            <div wire:loading wire:target="uploads" style="width:100%;">
                <div style="font-size:12px;color:var(--accent-light);margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                    <i class="fa-solid fa-spinner fa-spin"></i> Subiendo archivos...
                </div>
                <div style="height:4px;background:rgba(255,255,255,0.1);border-radius:2px;overflow:hidden;">
                    <div style="height:100%;background:var(--accent-light);width:100%;animation:indeterminate 1.5s infinite linear;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content: File Explorer --}}
    <div class="glass" style="flex:1;display:flex;flex-direction:column;padding:0;overflow:hidden;">
        
        {{-- Toolbar & Breadcrumb --}}
        <div style="padding:16px 20px;border-bottom:1px solid var(--glass-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
            <div style="display:flex;align-items:center;gap:8px;font-family:monospace;font-size:14px;">
                <button wire:click="navigateUp" class="btn btn-ghost btn-sm" style="padding:4px 8px;" @if($currentPath === '') disabled @endif>
                    <i class="fa-solid fa-arrow-up"></i>
                </button>
                <span style="color:var(--text-muted);margin:0 4px;">|</span>
                <i class="fa-solid fa-folder-open" style="color:var(--accent-light);"></i>
                <span wire:click="navigate('')" style="color:var(--accent-light);cursor:pointer;font-weight:700;">root</span>
                @foreach($breadcrumbs as $bc)
                <span style="color:var(--text-muted);">/</span>
                <span wire:click="navigate('{{ ltrim($bc['path'], '/') }}')" style="color:var(--text-primary);cursor:pointer;">{{ $bc['name'] }}</span>
                @endforeach
            </div>

            <div style="display:flex;gap:10px;">
                <button wire:click="$set('showCreateFolderModal', true)" class="btn btn-ghost btn-sm">
                    <i class="fa-solid fa-folder-plus"></i> Carpeta
                </button>
                <button wire:click="$set('showCreateFileModal', true)" class="btn btn-ghost btn-sm">
                    <i class="fa-solid fa-file-plus"></i> Archivo
                </button>
                <label class="btn btn-primary btn-sm" style="cursor:pointer;margin:0;">
                    <i class="fa-solid fa-upload"></i> Subir
                    <input type="file" wire:model.live="uploads" multiple style="display:none;">
                </label>
            </div>
        </div>

        {{-- Alerts --}}
        @if($successMessage)
            <div style="padding:10px 20px;background:rgba(39,201,63,0.1);color:var(--success);font-size:13px;border-bottom:1px solid rgba(39,201,63,0.2);">
                <i class="fa-solid fa-check-circle"></i> {{ $successMessage }}
            </div>
        @endif
        @if($errorMessage)
            <div style="padding:10px 20px;background:rgba(255,95,86,0.1);color:var(--danger);font-size:13px;border-bottom:1px solid rgba(255,95,86,0.2);">
                <i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}
            </div>
        @endif

        {{-- File List --}}
        <div style="flex:1;overflow-y:auto;padding:0;">
            <table class="table" style="width:100%;margin:0;border:none;">
                <thead style="position:sticky;top:0;background:var(--glass-bg);backdrop-filter:blur(10px);z-index:10;">
                    <tr>
                        <th style="padding-left:20px;width:45%;border-bottom:1px solid var(--glass-border);">Nombre</th>
                        <th style="width:12%;border-bottom:1px solid var(--glass-border);">Tamaño</th>
                        <th style="width:12%;border-bottom:1px solid var(--glass-border);">Permisos</th>
                        <th style="width:15%;border-bottom:1px solid var(--glass-border);">Modificado</th>
                        <th style="text-align:right;padding-right:20px;width:16%;border-bottom:1px solid var(--glass-border);">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @if(empty($items))
                    <tr>
                        <td colspan="5" style="text-align:center;padding:60px;color:var(--text-muted);">
                            <i class="fa-regular fa-folder-open" style="font-size:32px;opacity:0.3;margin-bottom:12px;display:block;"></i>
                            Carpeta vacía
                        </td>
                    </tr>
                    @endif

                    @foreach($items as $item)
                    <tr style="transition:background 0.2s;">
                        <td style="padding-left:20px;">
                            @if($item['is_dir'])
                                <div wire:click="navigate('{{ ltrim($currentPath . '/' . $item['name'], '/') }}')" style="cursor:pointer;display:flex;align-items:center;gap:12px;color:var(--text-primary);font-weight:600;">
                                    <i class="fa-solid fa-folder" style="font-size:18px;color:#60a5fa;"></i>
                                    {{ $item['name'] }}
                                </div>
                            @else
                                <div style="display:flex;align-items:center;gap:12px;color:var(--text-primary);">
                                    @php
                                        $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                                        $icon = 'fa-file-lines';
                                        $color = 'var(--text-secondary)';
                                        if (in_array($ext, ['php', 'js', 'css', 'html', 'json'])) { $icon = 'fa-file-code'; $color = '#a78bfa'; }
                                        elseif (in_array($ext, ['jpg', 'png', 'gif', 'svg', 'webp'])) { $icon = 'fa-file-image'; $color = '#34d399'; }
                                        elseif (in_array($ext, ['zip', 'tar', 'gz'])) { $icon = 'fa-file-zipper'; $color = '#fbbf24'; }
                                    @endphp
                                    <i class="fa-solid {{ $icon }}" style="font-size:18px;color:{{ $color }};"></i>
                                    {{ $item['name'] }}
                                </div>
                            @endif
                        </td>
                        <td style="font-size:12px;color:var(--text-secondary);">
                            @if($item['is_dir'])
                                —
                            @else
                                @php
                                    $bytes = $item['size'];
                                    $units = ['B', 'KB', 'MB', 'GB'];
                                    for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) { $bytes /= 1024; }
                                    $sizeFormatted = round($bytes, 1) . ' ' . $units[$i];
                                @endphp
                                {{ $sizeFormatted }}
                            @endif
                        </td>
                        <td>
                            <button wire:click="openChmodModal('{{ $item['name'] }}', '{{ $item['permissions'] }}')" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:var(--text-muted);border-radius:4px;padding:2px 6px;font-family:monospace;font-size:11px;cursor:pointer;transition:all 0.2s;">
                                {{ $item['permissions'] }}
                            </button>
                        </td>
                        <td style="font-size:12px;color:var(--text-secondary);">
                            {{ date('d M Y, H:i', $item['updated_at']) }}
                        </td>
                        <td style="text-align:right;padding-right:20px;">
                            <div style="display:inline-flex;gap:4px;">
                                @if(!$item['is_dir'])
                                    @if(in_array(strtolower(pathinfo($item['name'], PATHINFO_EXTENSION)), ['php', 'js', 'css', 'html', 'htm', 'txt', 'json', 'md', 'env', 'ini', 'conf']))
                                    <button wire:click="editFile('{{ $item['name'] }}')" class="btn btn-ghost btn-sm" title="Editar código" style="padding:4px 8px;">
                                        <i class="fa-solid fa-pen-nib" style="color:var(--accent-light);"></i>
                                    </button>
                                    @endif
                                    <button wire:click="downloadItem('{{ $item['name'] }}')" class="btn btn-ghost btn-sm" title="Descargar" style="padding:4px 8px;">
                                        <i class="fa-solid fa-download"></i>
                                    </button>
                                @endif

                                @if($item['is_dir'])
                                    <button wire:click="zipItem('{{ $item['name'] }}')" class="btn btn-ghost btn-sm" title="Comprimir Zip" style="padding:4px 8px;">
                                        <i class="fa-solid fa-file-zipper" style="color:var(--warning);"></i>
                                    </button>
                                @elseif(in_array(strtolower(pathinfo($item['name'], PATHINFO_EXTENSION)), ['zip', 'tar', 'gz']))
                                    <button wire:click="unzipItem('{{ $item['name'] }}')" class="btn btn-ghost btn-sm" title="Extraer Aquí" style="padding:4px 8px;">
                                        <i class="fa-solid fa-box-open" style="color:var(--success);"></i>
                                    </button>
                                @endif

                                <button wire:click="openRenameModal('{{ $item['name'] }}')" class="btn btn-ghost btn-sm" title="Renombrar" style="padding:4px 8px;">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button wire:click="deleteItem('{{ $item['name'] }}')" class="btn btn-ghost btn-sm" title="Eliminar" onclick="return confirm('¿Seguro que desea eliminar {{ $item['name'] }} de forma permanente?')" style="padding:4px 8px;color:var(--danger);">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modals (Create Folder, Create File, Chmod, Rename) --}}
    @if($showCreateFolderModal)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="width:100%;max-width:350px;padding:24px;border-radius:12px;">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;"><i class="fa-solid fa-folder-plus" style="color:var(--accent-light);"></i> Nueva Carpeta</h3>
            <input type="text" wire:model="newFolderName" class="form-input" placeholder="Nombre de carpeta..." autofocus>
            @error('newFolderName') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button wire:click="$set('showCreateFolderModal', false)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="createFolder" class="btn btn-primary btn-sm">Crear</button>
            </div>
        </div>
    </div>
    @endif

    @if($showCreateFileModal)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="width:100%;max-width:350px;padding:24px;border-radius:12px;">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;"><i class="fa-solid fa-file-plus" style="color:var(--accent-light);"></i> Nuevo Archivo</h3>
            <input type="text" wire:model="newFileName" class="form-input" placeholder="ej. index.php" autofocus>
            @error('newFileName') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button wire:click="$set('showCreateFileModal', false)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="createFile" class="btn btn-primary btn-sm">Crear</button>
            </div>
        </div>
    </div>
    @endif

    @if($chmodPath)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="width:100%;max-width:350px;padding:24px;border-radius:12px;">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;"><i class="fa-solid fa-shield-halved" style="color:var(--accent-light);"></i> Permisos (Chmod)</h3>
            <input type="text" wire:model="chmodOctal" class="form-input" placeholder="0644" autofocus>
            @error('chmodOctal') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button wire:click="$set('chmodPath', null)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="saveChmod" class="btn btn-primary btn-sm">Guardar</button>
            </div>
        </div>
    </div>
    @endif

    @if($renamingPath)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="width:100%;max-width:350px;padding:24px;border-radius:12px;">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;"><i class="fa-solid fa-pen" style="color:var(--accent-light);"></i> Renombrar</h3>
            <input type="text" wire:model="newName" class="form-input" placeholder="Nuevo nombre..." autofocus>
            @error('newName') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button wire:click="$set('renamingPath', null)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="renameItem" class="btn btn-primary btn-sm">Renombrar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Advanced Monaco Editor Overlay --}}
    @if($editingPath)
    <div style="position:fixed;inset:0;z-index:300;background:rgba(10,13,24,0.98);display:flex;flex-direction:column;backdrop-filter:blur(10px);">
        {{-- Editor Header --}}
        <div style="background:rgba(255,255,255,0.03);padding:12px 24px;border-bottom:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:36px;height:36px;background:rgba(99,102,241,0.2);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-code" style="color:var(--accent-light);"></i>
                </div>
                <div>
                    <strong style="font-size:14px;color:var(--text-primary);font-family:monospace;letter-spacing:0.5px;">{{ basename($editingPath) }}</strong>
                    <div style="font-size:11px;color:var(--text-muted);">{{ ltrim($editingPath, '/') }}</div>
                </div>
            </div>
            <div style="display:flex;gap:12px;">
                <button onclick="saveMonacoContent()" class="btn btn-primary" style="height:36px;background:var(--success);border-color:var(--success);color:black;">
                    <i class="fa-solid fa-save"></i> Guardar
                </button>
                <button wire:click="$set('editingPath', null)" class="btn btn-ghost" style="height:36px;background:rgba(255,255,255,0.1);">
                    Cerrar
                </button>
            </div>
        </div>

        {{-- Monaco Editor Container --}}
        <div style="flex:1;position:relative;background:#1e1e1e;">
            <div id="monaco-editor-container" style="position:absolute;inset:0;width:100%;height:100%;"></div>
        </div>
    </div>
    @endif

    {{-- Monaco Initialization --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/require.js/2.3.6/require.min.js"></script>
    <script>
        let lpEditor = null;
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

            require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.39.0/min/vs' } });
            require(['vs/editor/editor.main'], function() {
                const container = document.getElementById('monaco-editor-container');
                if (container) {
                    container.innerHTML = '';
                    lpEditor = monaco.editor.create(container, {
                        value: content,
                        language: lang,
                        theme: 'vs-dark',
                        fontSize: 14,
                        automaticLayout: true,
                        minimap: { enabled: true }
                    });
                }
            });
        });
        function saveMonacoContent() {
            if (lpEditor) {
                @this.call('saveFileContent', lpEditor.getValue());
            }
        }
    </script>
</div>
<style>
@keyframes indeterminate {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
</style>
