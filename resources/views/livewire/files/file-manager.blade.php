<div class="fm-container" x-data="{ selectedAll: false, isUploading: false, progress: 0,
        isDragOver: false, dragCounter: 0,
        ctx: { open: false, x: 0, y: 0, item: null },
        openCtx(e, item) {
            this.ctx.item = item;
            this.ctx.x = e.clientX;
            this.ctx.y = e.clientY;
            this.ctx.open = true;
            this.$nextTick(() => {
                const m = this.$refs.ctxmenu;
                if (!m) return;
                const r = m.getBoundingClientRect();
                this.ctx.x = Math.max(6, Math.min(this.ctx.x, window.innerWidth - r.width - 10));
                this.ctx.y = Math.max(6, Math.min(this.ctx.y, window.innerHeight - r.height - 10));
            });
        },
        closeCtx() { this.ctx.open = false; },
        handleDragEnter(e) {
            e.preventDefault();
            this.dragCounter++;
            if (e.dataTransfer && e.dataTransfer.types.includes('Files')) {
                this.isDragOver = true;
            }
        },
        handleDragOver(e) {
            e.preventDefault();
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
        },
        handleDragLeave(e) {
            e.preventDefault();
            this.dragCounter--;
            if (this.dragCounter <= 0) {
                this.dragCounter = 0;
                this.isDragOver = false;
            }
        },
        handleDrop(e, fallbackPath) {
            e.preventDefault();
            this.dragCounter = 0;
            this.isDragOver = false;
            if (this.hasFileDrag(e)) {
                this.fmDragDrop(e, fallbackPath);
                return;
            }
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.$refs.dropFileInput.files = files;
                this.$refs.dropFileInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
        hasFileDrag(e) {
            return Array.from(e.dataTransfer && e.dataTransfer.types ? e.dataTransfer.types : [])
                .includes('application/x-larapanel-files');
        },
        fmDragStart(e, name) {
            if (e.target.closest('button, input, a, select, label')) { e.preventDefault(); return; }
            let items = [name];
            const checked = Array.from(document.querySelectorAll('.file-checkbox:checked')).map(cb => cb.value);
            if (checked.indexOf(name) !== -1) { items = checked; }
            try {
                e.dataTransfer.setData('application/x-larapanel-files', JSON.stringify({ items: items }));
                e.dataTransfer.effectAllowed = (e.ctrlKey || e.metaKey) ? 'copy' : 'copyMove';
            } catch (err) {}
        },
        fmDragEnd(e) {
            this.clearDropTargets();
        },
        clearDropTargets() {
            document.querySelectorAll('.fm-drop-target').forEach(el => {
                el._fdrag = 0;
                el.classList.remove('fm-drop-target');
            });
        },
        fmDropTargetEnter(e) {
            if (!this.hasFileDrag(e)) return;
            e.preventDefault();
            const el = e.currentTarget;
            el._fdrag = (el._fdrag || 0) + 1;
            el.classList.add('fm-drop-target');
        },
        fmDropTargetLeave(e) {
            if (!this.hasFileDrag(e)) return;
            e.preventDefault();
            const el = e.currentTarget;
            el._fdrag = (el._fdrag || 0) - 1;
            if (el._fdrag <= 0) {
                el._fdrag = 0;
                el.classList.remove('fm-drop-target');
            }
        },
        fmContainerDragOver(e) {
            if (!this.hasFileDrag(e)) return;
            e.preventDefault();
            if (e.dataTransfer) e.dataTransfer.dropEffect = (e.ctrlKey || e.metaKey) ? 'copy' : 'move';
        },
        fmDragDrop(e, destPath) {
            if (!this.hasFileDrag(e)) return;
            e.preventDefault();
            e.stopPropagation();
            this.clearDropTargets();
            let payload = null;
            try { payload = JSON.parse(e.dataTransfer.getData('application/x-larapanel-files') || 'null'); } catch (err) {}
            if (!payload || !payload.items || !payload.items.length) return;
            this.$wire.dragDropItems(payload.items, destPath, !!(e.ctrlKey || e.metaKey));
        }
    }"
     @contextmenu="closeCtx()">
    


    {{-- Left Sidebar: Tree Navigation --}}
    <div class="glass fm-sidebar">
        <div class="fm-sidebar-header">
            <h3>
                <i class="fa-solid fa-hard-drive" style="color:var(--accent-light);font-size:18px;"></i> 
                <span>Explorador</span>
            </h3>
        </div>
        
        <div class="fm-sidebar-content">
            <button wire:click="navigate('')" class="btn btn-ghost" style="width:100%;text-align:left;justify-content:flex-start;padding:10px 14px;border-radius:8px;background:{{ $currentPath === '' && !$showTrash ? 'rgba(99,102,241,0.15)' : 'transparent' }};color:{{ $currentPath === '' && !$showTrash ? 'var(--accent-light)' : 'var(--text-secondary)' }};font-size:13px;font-weight:600;margin-bottom:10px;">
                <i class="fa-solid fa-server" style="width:20px;font-size:14px;color:{{ $currentPath === '' && !$showTrash ? 'var(--accent-light)' : 'var(--text-muted)' }};"></i> /var/www
            </button>

            <button wire:click="openTrash" class="btn btn-ghost" style="width:100%;text-align:left;justify-content:flex-start;padding:10px 14px;border-radius:8px;background:{{ $showTrash ? 'rgba(248,113,113,0.15)' : 'transparent' }};color:{{ $showTrash ? '#f87171' : 'var(--text-secondary)' }};font-size:13px;font-weight:600;margin-bottom:10px;">
                <i class="fa-solid fa-trash-can" style="width:20px;font-size:14px;color:{{ $showTrash ? '#f87171' : 'var(--text-muted)' }};"></i> Papelera
                @if(!empty($trashCount))
                    <span style="margin-left:auto;background:rgba(248,113,113,0.15);color:#f87171;border-radius:10px;font-size:11px;font-weight:700;padding:2px 8px;">{{ $trashCount }}</span>
                @else
                    <span style="margin-left:auto;font-size:11px;color:var(--text-muted);">0</span>
                @endif
            </button>
            
            {{-- Favorite Directories --}}
            <div class="fm-sidebar-label" style="margin-top:6px;">Favoritos</div>
            <div style="display:flex;flex-direction:column;gap:2px;margin-bottom:10px;">
                @forelse($favoritesList as $fav)
                    <div style="display:flex;align-items:center;padding:5px 12px;border-radius:8px;background:{{ $currentPath === $fav['path'] ? 'rgba(99,102,241,0.15)' : 'transparent' }};transition:background 0.2s;{{ $fav['exists'] ? '' : 'opacity:0.45;' }}">
                        <div wire:click="navigate('{{ addslashes($fav['path']) }}')" style="display:flex;align-items:center;flex:1;gap:8px;font-size:13px;font-weight:600;cursor:pointer;color:{{ $currentPath === $fav['path'] ? 'var(--accent-light)' : 'var(--text-secondary)' }};min-width:0;" title="{{ $fav['exists'] ? '/var/www/' . $fav['path'] : 'El directorio ya no existe' }}">
                            <i class="fa-solid fa-star" style="font-size:10px;color:#fbbf24;"></i>
                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $fav['name'] }}</span>
                            @if(!$fav['exists'])<i class="fa-solid fa-triangle-exclamation" style="font-size:10px;color:#f87171;margin-left:2px;" title="El directorio ya no existe"></i>@endif
                        </div>
                        <button wire:click.stop="toggleFavorite('{{ addslashes($fav['path']) }}')" title="Quitar de favoritos" style="background:transparent;border:none;color:var(--text-muted);cursor:pointer;padding:2px 4px;border-radius:4px;opacity:0.5;transition:all 0.2s;" onmouseover="this.style.opacity='1';this.style.color='#f87171'" onmouseout="this.style.opacity='0.5';this.style.color='var(--text-muted)'">
                            <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
                        </button>
                    </div>
                @empty
                    <div style="font-size:11px;color:var(--text-muted);padding:0 12px 6px;font-style:italic;">
                        Sin favoritos. Haz clic derecho sobre una carpeta para añadirla.
                    </div>
                @endforelse
            </div>

            <div class="fm-sidebar-label">Directorios</div>
            
            <div style="display:flex;flex-direction:column;gap:2px;">
                @include('livewire.files.tree-node', ['nodes' => $tree, 'level' => 0])
            </div>
            
            {{-- Quick Stats --}}
            <div class="fm-storage-widget">
                <div style="font-size:12px;font-weight:700;color:var(--text-secondary);margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;">
                    <span>Almacenamiento VPS</span>
                    <i class="fa-solid fa-circle-info" style="color:var(--text-muted);" title="{{ \App\Services\MonitoringService::formatBytes($diskInfo['used'] ?? 0) }} usados de {{ \App\Services\MonitoringService::formatBytes($diskInfo['total'] ?? 0) }}"></i>
                </div>
                <div class="fm-storage-bar">
                    <div class="fm-storage-fill" style="width:{{ $diskInfo['usage'] ?? 0 }}%;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);">
                    <span>Uso aprox: {{ $diskInfo['usage'] ?? 0 }}%</span>
                    <span>{{ $serverLabel }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Panel: Explorer & Actions --}}
    <div class="glass fm-main"
         x-on:dragenter="handleDragEnter($event)"
         x-on:dragover="handleDragOver($event)"
         x-on:dragleave="handleDragLeave($event)"
         x-on:drop="handleDrop($event, @js($currentPath))"
         style="position:relative;">
        {{-- Drop Zone Overlay --}}
        <div x-show="isDragOver" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fm-dropzone-overlay" style="display:none;">
            <div class="fm-dropzone-card">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size:48px;color:var(--accent-light);margin-bottom:16px;"></i>
                <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">Suelta los archivos aquí</h3>
                <p style="font-size:13px;color:var(--text-muted);">Se subirán a: <strong>/{{ $currentPath ?: 'var/www' }}</strong></p>
            </div>
        </div>
        <input type="file" wire:model.live="uploads" multiple x-ref="dropFileInput" style="display:none;">

        @if($showTrash)
        {{-- ============ PAPELERA ============ --}}
        <div class="fm-toolbar">
            <div class="fm-breadcrumb">
                <button wire:click="closeTrash" class="btn btn-ghost btn-sm" style="padding:6px 10px;border-radius:6px;background:rgba(255,255,255,0.03);">
                    <i class="fa-solid fa-arrow-left" style="font-size:14px;"></i>
                </button>
                <span style="color:var(--glass-border);">|</span>
                <i class="fa-solid fa-trash-can" style="color:#f87171;font-size:15px;margin-right:2px;"></i>
                <span style="color:#f87171;cursor:pointer;font-weight:700;">Papelera</span>
                <span style="color:var(--text-muted);font-size:12px;margin-left:6px;">
                    ({{ $trashCount }} {{ $trashCount === 1 ? 'elemento' : 'elementos' }}) — los archivos eliminados se guardan aquí y se pueden restaurar
                </span>
            </div>

            <div class="fm-toolbar-actions">
                <button class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;color:var(--text-muted);{{ $trashCount === 0 ? 'opacity:0.4;cursor:not-allowed;' : '' }}" @if($trashCount === 0) disabled @endif wire:click="confirmPurge('', 'all')">
                    <i class="fa-solid fa-brush" style="color:#f87171;"></i>
                    <span>Vaciar Papelera</span>
                </button>
            </div>
        </div>

        @if($successMessage)
            <div class="fm-alert fm-alert-success">
                <i class="fa-solid fa-circle-check" style="font-size:16px;"></i> 
                <span>{{ $successMessage }}</span>
            </div>
        @endif
        @if($errorMessage)
            <div class="fm-alert fm-alert-error">
                <i class="fa-solid fa-circle-exclamation" style="font-size:16px;"></i> 
                <span>{{ $errorMessage }}</span>
            </div>
        @endif

        <div style="flex:1;overflow-y:auto;padding:0;position:relative;" class="table-responsive">
            <table class="lp-table fm-table">
                <thead>
                    <tr>
                        <th style="padding:14px 20px;width:38%;text-align:left;font-weight:700;color:var(--text-muted);">Elemento</th>
                        <th style="width:28%;text-align:left;font-weight:700;color:var(--text-muted);">Ubicación original</th>
                        <th style="width:10%;text-align:left;font-weight:700;color:var(--text-muted);">Tamaño</th>
                        <th style="width:14%;text-align:left;font-weight:700;color:var(--text-muted);">Eliminado</th>
                        <th style="text-align:right;padding-right:24px;width:18%;font-weight:700;color:var(--text-muted);">Acciones</th>
                    </tr>
                </thead>
                <tbody style="background:transparent;">
                    @forelse($trashItems as $trash)
                    <tr wire:key="trash-row-{{ $trash['id'] }}">
                        <td style="padding:12px 20px;vertical-align:middle;">
                            <div style="display:flex;align-items:center;gap:12px;color:var(--text-primary);">
                                <i class="fa-solid {{ $trash['is_dir'] ? 'fa-folder' : 'fa-file-lines' }}" style="font-size:18px;color:{{ $trash['is_dir'] ? '#38bdf8' : 'var(--text-secondary)' }};"></i>
                                <span style="font-weight:600;">{{ $trash['name'] }}
                                    @if(!$trash['exists'])
                                        <i class="fa-solid fa-triangle-exclamation" style="font-size:11px;color:#f87171;margin-left:4px;" title="El contenido ya no existe en el disco"></i>
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td style="color:var(--text-secondary);vertical-align:middle;font-family:monospace;font-size:12px;">
                            @if($trash['original'] === '')
                                /var/www/
                            @else
                                /{{ $trash['original'] }}
                            @endif
                        </td>
                        <td style="color:var(--text-secondary);vertical-align:middle;font-family:monospace;font-size:12px;">
                            @php
                                $tb = $trash['size'];
                                $tu = ['B', 'KB', 'MB', 'GB'];
                                for ($i = 0; $tb >= 1024 && $i < count($tu) - 1; $i++) { $tb /= 1024; }
                            @endphp
                            @if($trash['is_dir'])
                                <span style="color:var(--text-muted);font-family:monospace;font-size:11px;">DIR</span>
                            @else
                                {{ round($tb, 1) }} {{ $tu[$i] }}
                            @endif
                        </td>
                        <td style="color:var(--text-secondary);vertical-align:middle;font-size:12px;">
                            @if($trash['deleted_at'])
                                {{ date('d M Y H:i', $trash['deleted_at']) }}
                            @else
                                <span style="color:var(--text-muted);">—</span>
                            @endif
                        </td>
                        <td style="text-align:right;padding-right:24px;vertical-align:middle;">
                            <div style="display:inline-flex;gap:4px;">
                                <button wire:click="restoreTrashItem('{{ $trash['id'] }}')" class="btn btn-ghost btn-sm" title="Restaurar a su ubicación original" style="padding:6px 10px;border-radius:6px;background:rgba(16,185,129,0.1);" @if(!$trash['exists']) disabled @endif>
                                    <i class="fa-solid fa-rotate-left" style="color:#6ee7b7;font-size:14px;"></i>
                                </button>
                                <button wire:click="confirmPurge('{{ $trash['id'] }}', 'item')" class="btn btn-ghost btn-sm" title="Eliminar definitivamente" style="padding:6px 10px;border-radius:6px;color:var(--danger);">
                                    <i class="fa-solid fa-trash-can" style="font-size:14px;"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align:center;padding:80px 20px;color:var(--text-muted);">
                            <div style="display:flex;flex-direction:column;align-items:center;gap:16px;">
                                <div style="width:64px;height:64px;background:rgba(255,255,255,0.02);border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid var(--glass-border);">
                                    <i class="fa-regular fa-trash-can" style="font-size:26px;color:var(--text-muted);opacity:0.6;"></i>
                                </div>
                                <span style="font-size:14px;font-weight:600;">La Papelera está vacía</span>
                                <span style="font-size:12px;color:var(--text-muted);">Los elementos que elimines se guardarán aquí para poder restaurarlos.</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @else

        {{-- Top Toolbar --}}
        <div class="fm-toolbar">
            {{-- Breadcrumb path navigation --}}
            <div class="fm-breadcrumb">
                <button wire:click="navigateUp" class="btn btn-ghost btn-sm" style="padding:6px 10px;border-radius:6px;background:rgba(255,255,255,0.03);" @if($currentPath === '') disabled style="opacity:0.3;cursor:not-allowed;" @endif>
                    <i class="fa-solid fa-level-up-alt" style="transform:rotate(-90deg);"></i>
                </button>
                <span style="color:var(--glass-border);">|</span>
                <i class="fa-solid fa-folder-open" style="color:var(--accent-light);font-size:15px;margin-right:2px;"></i>
                <span wire:click="navigate('')" style="color:var(--accent-light);cursor:pointer;font-weight:700;">var/www</span>
                @foreach($breadcrumbs as $bc)
                    <span style="color:var(--text-muted);">/</span>
                    <span wire:click="navigate('{{ ltrim($bc['path'], '/') }}')" style="color:var(--text-primary);cursor:pointer;hover:color:var(--accent-light);">{{ $bc['name'] }}</span>
                @endforeach
            </div>

            {{-- New Item Actions --}}
            <div class="fm-toolbar-actions">
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
            <div class="fm-alert fm-alert-success">
                <i class="fa-solid fa-circle-check" style="font-size:16px;"></i> 
                <span>{{ $successMessage }}</span>
            </div>
        @endif
        @if($errorMessage)
            <div class="fm-alert fm-alert-error">
                <i class="fa-solid fa-circle-exclamation" style="font-size:16px;"></i> 
                <span>{{ $errorMessage }}</span>
            </div>
        @endif

        {{-- File List Container --}}
        <div style="flex:1;overflow-y:auto;padding:0;position:relative;" class="table-responsive" @scroll="closeCtx()"
             @dragenter="fmDropTargetEnter($event)"
             @dragleave="fmDropTargetLeave($event)"
             @dragover.prevent="fmContainerDragOver($event)">
            {{-- Loading indicator --}}
            <div wire:loading.delay wire:target="navigate, navigateUp" style="position:absolute;inset:0;z-index:20;background:rgba(15,23,42,0.6);backdrop-filter:blur(2px);">
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);display:flex;align-items:center;gap:10px;color:var(--accent-light);font-size:13px;font-weight:600;">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size:18px;"></i>
                    <span>Cargando...</span>
                </div>
            </div>
            <table class="lp-table fm-table">
                <thead>
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
                    @php
                        $itemFavPath = ltrim($currentPath . '/' . $item['name'], '/');
                        $isItemFav = in_array($itemFavPath, $favorites);
                    @endphp
                    <tr wire:key="file-row-{{ md5($item['name'] . '-' . $item['updated_at']) }}"
                        draggable="true"
                        @dragstart="fmDragStart($event, @js($item['name']))"
                        @dragend="fmDragEnd($event)"
                        @if($item['is_dir'])
                            @dragenter="fmDropTargetEnter($event)"
                            @dragleave="fmDropTargetLeave($event)"
                            @dragover.prevent.stop="fmContainerDragOver($event)"
                            @drop.prevent.stop="fmDragDrop($event, @js(ltrim(($currentPath ? $currentPath . '/' : '') . $item['name'], '/')))"
                        @endif
                        class="fm-row {{ in_array($item['name'], $selectedItems) ? 'fm-selected' : '' }}"
                        @contextmenu.prevent.stop="openCtx($event, @js([
                            'name' => $item['name'],
                            'isDir' => $item['is_dir'],
                            'perms' => $item['permissions'],
                            'isBinary' => $isBinary,
                            'isKnownText' => $isKnownText,
                            'path' => $itemFavPath,
                            'isFav' => $isItemFav,
                            'ext' => $ext,
                        ]))"
                    >
                        <td style="padding:12px 20px;text-align:center;vertical-align:middle;">
                            <input type="checkbox" value="{{ $item['name'] }}" wire:model.live="selectedItems" class="file-checkbox" style="width:16px;height:16px;accent-color:var(--accent-light);cursor:pointer;border-radius:4px;" x-on:click.stop>
                        </td>
                        <td style="padding:12px 10px;vertical-align:middle;" 
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
                            @if($item['is_dir'])
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div wire:click="navigate('{{ ltrim($currentPath . '/' . $item['name'], '/') }}')" style="cursor:pointer;display:flex;align-items:center;gap:12px;color:var(--text-primary);font-weight:600;transition:color 0.2s;" onmouseover="this.style.color='var(--accent-light)'" onmouseout="this.style.color='var(--text-primary)'">
                                        <i class="fa-solid fa-folder" style="font-size:18px;color:#38bdf8;"></i>
                                        <span>{{ $item['name'] }}</span>
                                    </div>
                                    <button wire:click.stop="toggleFavorite('{{ addslashes($itemFavPath) }}')" class="fm-star-btn {{ $isItemFav ? 'fm-fav' : '' }}" title="{{ $isItemFav ? 'Quitar de favoritos' : 'Añadir a favoritos' }}">
                                        <i class="fa-{{ $isItemFav ? 'solid' : 'regular' }} fa-star" style="font-size:12px;"></i>
                                    </button>
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
                                    <button wire:click="prepareZip('{{ addslashes($item['name']) }}')" class="btn btn-ghost btn-sm" title="Comprimir Zip" style="padding:6px 10px;border-radius:6px;">
                                        <i class="fa-solid fa-file-zipper" style="color:var(--warning);font-size:14px;"></i>
                                    </button>
                                @elseif(strtolower(pathinfo($item['name'], PATHINFO_EXTENSION)) === 'zip')
                                    <button wire:click="startUnzip('{{ $item['name'] }}')" class="btn btn-ghost btn-sm" title="Extraer Aquí" style="padding:6px 10px;border-radius:6px;">
                                        <i class="fa-solid fa-box-open" style="color:var(--success);font-size:14px;"></i>
                                    </button>
                                @endif

                                <button wire:click="openRenameModal('{{ $item['name'] }}')" class="btn btn-ghost btn-sm" title="Renombrar" style="padding:6px 10px;border-radius:6px;">
                                    <i class="fa-solid fa-pen-to-square" style="font-size:14px;"></i>
                                </button>
                                <button wire:click="confirmDelete('{{ addslashes($item['name']) }}')" class="btn btn-ghost btn-sm" title="Eliminar" style="padding:6px 10px;border-radius:6px;color:var(--danger);">
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
        <div class="fm-float-bar">
            <div style="font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;">
                <span class="fm-float-count">{{ count($selectedItems) }}</span>
                <span style="color:var(--text-primary);">seleccionados</span>
            </div>
            
            <div class="fm-float-divider"></div>
            
            <div style="display:flex;gap:8px;">
                <button wire:click="prepareZip" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px;background:rgba(251,191,36,0.1);color:var(--warning);border-radius:8px;padding:6px 12px;font-size:12px;">
                    <i class="fa-solid fa-file-zipper"></i> Comprimir
                </button>
                <button wire:click="prepareBulkMove" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px;background:rgba(99,102,241,0.1);color:var(--accent-light);border-radius:var(--radius-sm);padding:6px 12px;font-size:12px;">
                    <i class="fa-solid fa-arrows-up-down-left-right"></i> Mover
                </button>
                <button wire:click="prepareBulkCopy" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px;background:rgba(16,185,129,0.1);color:#6ee7b7;border-radius:8px;padding:6px 12px;font-size:12px;">
                    <i class="fa-solid fa-clone"></i> Copiar
                </button>
                <button wire:click="confirmDelete()" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px;background:rgba(239,68,68,0.1);color:#f87171;border-radius:8px;padding:6px 12px;font-size:12px;">
                    <i class="fa-solid fa-trash-can"></i> Eliminar
                </button>
            </div>
            
            <div class="fm-float-divider"></div>
            
            <button wire:click="$set('selectedItems', [])" class="btn btn-ghost btn-sm" style="color:var(--text-muted);font-size:12px;padding:6px 10px;">
                Deseleccionar todo
            </button>
        </div>
        @endif
        @endif
    </div>

    {{-- Right-click Context Menu --}}
    <div x-show="ctx.open" x-cloak x-ref="ctxmenu"
         :style="`left:${ctx.x}px;top:${ctx.y}px`"
         class="fm-ctx-menu glass-elevated"
         @click.outside="closeCtx()"
         @keyup.escape.window="closeCtx()">
        <template x-if="ctx.item">
            <div>
                <div class="fm-ctx-header">
                    <i class="fa-solid" :class="ctx.item.isDir ? 'fa-folder' : 'fa-file-lines'" style="font-size:12px;color:var(--accent-light);"></i>
                    <span x-text="ctx.item.name"></span>
                </div>

                <button class="fm-ctx-item" x-show="ctx.item.isDir" @click="closeCtx(); $wire.navigate(ctx.item.path)">
                    <i class="fa-solid fa-folder-open" style="width:18px;color:#38bdf8;"></i> Abrir
                </button>
                <button class="fm-ctx-item" x-show="ctx.item.isDir" @click="closeCtx(); $wire.toggleFavorite(ctx.item.path)">
                    <i class="fa-star" style="width:18px;color:#fbbf24;" :class="ctx.item.isFav ? 'fa-solid' : 'fa-regular'"></i>
                    <span x-text="ctx.item.isFav ? 'Quitar de favoritos' : 'Añadir a favoritos'"></span>
                </button>
                <button class="fm-ctx-item" x-show="!ctx.item.isDir && !ctx.item.isBinary" @click="
                    if (ctx.item.isKnownText) { closeCtx(); $wire.editFile(ctx.item.name) }
                    else if (confirm('Este archivo tiene una extensión desconocida. ¿Intentar abrir como texto plano?')) { closeCtx(); $wire.editFile(ctx.item.name) }
                ">
                    <i class="fa-solid fa-code" style="width:18px;color:var(--accent-light);"></i> Editar código
                </button>
                <button class="fm-ctx-item" x-show="!ctx.item.isDir" @click="closeCtx(); $wire.downloadItem(ctx.item.name)">
                    <i class="fa-solid fa-download" style="width:18px;"></i> Descargar
                </button>

                <div class="fm-ctx-divider"></div>

                <button class="fm-ctx-item" x-show="ctx.item.isDir" @click="closeCtx(); $wire.prepareZip(ctx.item.name)">
                    <i class="fa-solid fa-file-zipper" style="width:18px;color:var(--warning);"></i> Comprimir en .zip
                </button>
                <button class="fm-ctx-item" x-show="!ctx.item.isDir && ctx.item.ext === 'zip'" @click="closeCtx(); $wire.startUnzip(ctx.item.name)">
                    <i class="fa-solid fa-box-open" style="width:18px;color:var(--success);"></i> Extraer aquí
                </button>
                <button class="fm-ctx-item" @click="closeCtx(); $wire.openRenameModal(ctx.item.name)">
                    <i class="fa-solid fa-pen-to-square" style="width:18px;"></i> Renombrar
                </button>
                <button class="fm-ctx-item" @click="closeCtx(); $wire.openChmodModal(ctx.item.name, ctx.item.perms)">
                    <i class="fa-solid fa-shield-halved" style="width:18px;"></i> Cambiar permisos…
                </button>

                <div class="fm-ctx-divider"></div>

                <button class="fm-ctx-item fm-ctx-danger" @click="closeCtx(); $wire.confirmDelete(ctx.item.name)">
                    <i class="fa-solid fa-trash-can" style="width:18px;"></i> Eliminar
                </button>
            </div>
        </template>
    </div>

    {{-- Modals (Create Folder, Create File, Chmod, Rename, Bulk Move, Bulk Copy, Delete) --}}
    
    {{-- Delete Modal --}}
    @if($showDeleteModal)
    <div class="fm-modal-backdrop">
        <div class="glass-elevated fm-modal" style="text-align:center;">
            <div class="fm-delete-icon">
                <i class="fa-solid fa-trash-can" style="color:var(--danger);font-size:20px;"></i>
            </div>
            <h3 class="fm-modal-title" style="justify-content:center;">Mover a la Papelera</h3>
            <p class="fm-delete-text">
                @if($isDeletingMultiple)
                    ¿Estás seguro de que deseas mover <strong>{{ count($selectedItems) }}</strong> elementos a la Papelera?
                @else
                    ¿Estás seguro de que deseas mover <strong>{{ $deletingItemName }}</strong> a la Papelera?
                @endif
                Podrás restaurarlo desde la Papelera hasta que la vacíes.
            </p>
            <div class="fm-modal-footer" style="justify-content:center;">
                <button wire:click="$set('showDeleteModal', false)" class="btn btn-ghost" style="flex:1;justify-content:center;">Cancelar</button>
                <button wire:click="executeDelete" class="btn btn-danger" style="flex:1;justify-content:center;">
                    <i class="fa-solid fa-trash"></i> Mover a Papelera
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Purge Confirmation Modal --}}
    @if($showPurgeModal)
    <div class="fm-modal-backdrop">
        <div class="glass-elevated fm-modal" style="text-align:center;">
            <div class="fm-delete-icon">
                <i class="fa-solid fa-brush" style="color:var(--danger);font-size:20px;"></i>
            </div>
            <h3 class="fm-modal-title" style="justify-content:center;">Eliminación definitiva</h3>
            <p class="fm-delete-text">
                @if($purgeAction === 'all')
                    ¿Estás seguro de que deseas <strong>vaciar la Papelera</strong>?
                    Esta acción es irreversible y no se puede deshacer.
                @else
                    ¿Estás seguro de que deseas <strong>eliminar definitivamente</strong> este elemento?
                    Esta acción es irreversible y no se puede deshacer.
                @endif
            </p>
            <div class="fm-modal-footer" style="justify-content:center;">
                <button wire:click="$set('showPurgeModal', false)" class="btn btn-ghost" style="flex:1;justify-content:center;">Cancelar</button>
                <button wire:click="executePurge" class="btn btn-danger" style="flex:1;justify-content:center;">
                    <i class="fa-solid fa-trash"></i> Eliminar definitivamente
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Create Folder Modal --}}
    @if($showCreateFolderModal)
    <div class="fm-modal-backdrop">
        <div class="glass-elevated fm-modal">
            <h3 class="fm-modal-title">
                <i class="fa-solid fa-folder-plus" style="color:var(--accent-light);"></i> 
                <span>Nueva Carpeta</span>
            </h3>
            <input type="text" wire:model="newFolderName" class="form-input" placeholder="Escribe el nombre de la carpeta..." autofocus style="width:100%;padding:10px;background:rgba(0,0,0,0.2);border:1px solid var(--glass-border);border-radius:8px;color:var(--text-primary);">
            @error('newFolderName') <div style="font-size:11px;color:#f87171;margin-top:6px;">{{ $message }}</div> @enderror
                <div class="fm-modal-footer">
                    <button wire:click="closeCreateFolderModal" class="btn btn-ghost btn-sm">Cancelar</button>
                    <button wire:click="createFolder" class="btn btn-primary btn-sm">Crear Carpeta</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Zip Selection Modal --}}
    @if($showZipModal)
    <div class="fm-modal-backdrop">
        <div class="glass-elevated fm-modal">
            <h3 class="fm-modal-title">
                <i class="fa-solid fa-file-zipper" style="color:var(--warning);"></i> 
                <span>Comprimir Selección</span>
            </h3>
            <label class="fm-modal-label">Nombre del archivo Zip:</label>
            <input type="text" wire:model="zipFileName" class="form-input" placeholder="ej. backup.zip" autofocus>
            @error('zipFileName') <div class="form-error">{{ $message }}</div> @enderror
            <div class="fm-modal-footer">
                <button wire:click="closeZipModal" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="zipSelected" class="btn btn-primary btn-sm">Comprimir</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Create File Modal --}}
    @if($showCreateFileModal)
    <div class="fm-modal-backdrop">
        <div class="glass-elevated fm-modal">
            <h3 class="fm-modal-title">
                <i class="fa-solid fa-file-circle-plus" style="color:var(--accent-light);"></i> 
                <span>Nuevo Archivo</span>
            </h3>
            <input type="text" wire:model="newFileName" class="form-input" placeholder="ej. index.php" autofocus>
            @error('newFileName') <div class="form-error">{{ $message }}</div> @enderror
            <div class="fm-modal-footer">
                <button wire:click="$set('showCreateFileModal', false)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="createFile" class="btn btn-primary btn-sm">Crear Archivo</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Chmod Permissions Modal --}}
    @if($chmodPath)
    <div class="fm-modal-backdrop">
        <div class="glass-elevated fm-modal">
            <h3 class="fm-modal-title">
                <i class="fa-solid fa-shield-halved" style="color:var(--accent-light);"></i> 
                <span>Cambiar Permisos (Chmod)</span>
            </h3>
            <label class="fm-modal-label">Modo octal:</label>
            <input type="text" wire:model="chmodOctal" class="form-input" placeholder="0755" autofocus style="font-family:monospace;letter-spacing:2px;font-size:16px;text-align:center;">
            @error('chmodOctal') <div class="form-error">{{ $message }}</div> @enderror
            <div class="fm-modal-footer">
                <button wire:click="$set('chmodPath', null)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="saveChmod" class="btn btn-primary btn-sm">Guardar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Rename Modal --}}
    @if($renamingPath)
    <div class="fm-modal-backdrop">
        <div class="glass-elevated fm-modal">
            <h3 class="fm-modal-title">
                <i class="fa-solid fa-pen-nib" style="color:var(--accent-light);"></i> 
                <span>Renombrar Recurso</span>
            </h3>
            <input type="text" wire:model="newName" class="form-input" placeholder="Nuevo nombre..." autofocus>
            @error('newName') <div class="form-error">{{ $message }}</div> @enderror
            <div class="fm-modal-footer">
                <button wire:click="$set('renamingPath', null)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="renameItem" class="btn btn-primary btn-sm">Renombrar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Bulk Move Modal --}}
    @if($showBulkMoveModal)
    <div class="fm-modal-backdrop">
        <div class="glass-elevated fm-modal fm-modal-lg">
            <h3 class="fm-modal-title">
                <i class="fa-solid fa-arrows-up-down-left-right" style="color:var(--accent-light);"></i> 
                <span>Mover a la carpeta</span>
            </h3>
            <label class="fm-modal-label">Directorio de destino (ruta relativa a la raíz web):</label>
            <input type="text" wire:model="bulkDestDirectory" class="form-input" placeholder="ej. html/tienda (vacío para la raíz)" autofocus style="font-family:monospace;">
            @error('bulkDestDirectory') <div class="form-error">{{ $message }}</div> @enderror
            <div class="fm-modal-footer">
                <button wire:click="$set('showBulkMoveModal', false)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="moveSelected" class="btn btn-primary btn-sm">Mover Elementos</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Bulk Copy Modal --}}
    @if($showBulkCopyModal)
    <div class="fm-modal-backdrop">
        <div class="glass-elevated fm-modal fm-modal-lg">
            <h3 class="fm-modal-title">
                <i class="fa-solid fa-clone" style="color:var(--accent-light);"></i> 
                <span>Copiar a la carpeta</span>
            </h3>
            <label class="fm-modal-label">Directorio de destino (ruta relativa a la raíz web):</label>
            <input type="text" wire:model="bulkDestDirectory" class="form-input" placeholder="ej. html/copias (vacío para la raíz)" autofocus style="font-family:monospace;">
            @error('bulkDestDirectory') <div class="form-error">{{ $message }}</div> @enderror
            <div class="fm-modal-footer">
                <button wire:click="$set('showBulkCopyModal', false)" class="btn btn-ghost btn-sm">Cancelar</button>
                <button wire:click="copySelected" class="btn btn-primary btn-sm">Copiar Elementos</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Advanced Monaco Editor Overlay --}}
    @if($editingPath)
    <div class="fm-editor-overlay" id="monaco-full-editor">
        {{-- Editor Header --}}
        <div class="fm-editor-header">
            <div style="display:flex;align-items:center;gap:16px;">
                <div class="fm-editor-icon">
                    <i class="fa-solid fa-code" style="color:var(--accent-light);font-size:18px;"></i>
                </div>
                <div class="fm-editor-info">
                    <strong>{{ basename($editingPath) }}</strong>
                    <div>/var/www/{{ ltrim($editingPath, '/') }}</div>
                </div>
            </div>
            
            {{-- Editor Status & Actions --}}
            <div class="fm-editor-actions">
                {{-- Save status indicator --}}
                <div id="editor-save-status" class="fm-editor-status">
                    <i class="fa-solid fa-circle-check" style="color:#22c55e;"></i>
                    <span>Listo</span>
                </div>

                {{-- Language selector --}}
                <select id="editor-language-select" onchange="changeEditorLanguage(this.value)" style="background:rgba(0,0,0,0.4);border:1px solid var(--glass-border);color:var(--text-primary);border-radius:6px;padding:6px 12px;font-size:12px;outline:none;cursor:pointer;">
                    <option value="plaintext">Texto Plano</option>
                    <option value="php">PHP</option>
                    <option value="javascript">JavaScript</option>
                    <option value="html">HTML</option>
                    <option value="css">CSS</option>
                    <option value="json">JSON</option>
                    <option value="markdown">Markdown</option>
                    <option value="shell">Shell Script</option>
                </select>

                <button onclick="saveMonacoContent()" class="fm-editor-save" onmousedown="this.style.transform='scale(0.97)'" onmouseup="this.style.transform='scale(1)'">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar <span style="font-size:10px;opacity:0.6;background:rgba(0,0,0,0.15);padding:2px 6px;border-radius:4px;margin-left:4px;font-family:monospace;">Ctrl+S</span>
                </button>
                
                <button wire:click="$set('editingPath', null)" class="btn btn-ghost fm-editor-close">
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
        const FM_ALLOWED_ORIGIN = window.location.origin;

        // Escuchar mensajes del iframe
        window.addEventListener('message', function(event) {
            if (event.origin !== FM_ALLOWED_ORIGIN) return;
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
                }, FM_ALLOWED_ORIGIN);
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
                }, FM_ALLOWED_ORIGIN);
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
                    iframe.contentWindow.postMessage({ action: 'getValue' }, FM_ALLOWED_ORIGIN);
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
            if (e.key === 'Escape') {
                const modal = document.getElementById('monaco-full-editor');
                if (modal) {
                    e.preventDefault();
                    @this.set('editingPath', null);
                }
            }
        });
    </script>

    {{-- Upload Progress Modal (vanilla JS, escucha en window) --}}
    <div id="fm-upload-modal" class="fm-upload-progress" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.75);backdrop-filter:blur(6px);z-index:99999;align-items:center;justify-content:center;">
        <div style="background:rgba(15,23,42,0.97);border:1px solid var(--glass-border);border-radius:14px;padding:32px;width:100%;max-width:420px;text-align:center;">
            <div style="font-size:44px;color:var(--accent-light);margin-bottom:14px;line-height:1;"><i class="fa-solid fa-cloud-arrow-up"></i></div>
            <h3 style="font-size:18px;font-weight:700;margin:0 0 6px;">Subiendo archivo...</h3>
            <p id="fm-upload-filename" style="font-size:13px;color:var(--text-secondary);margin:0 0 16px;word-break:break-all;">
                <span style="color:var(--text-muted);">Preparando...</span>
            </p>
            <div style="width:100%;height:8px;background:rgba(255,255,255,0.12);border-radius:5px;overflow:hidden;margin-bottom:10px;">
                <div id="fm-upload-bar" style="width:0%;height:100%;background:linear-gradient(90deg,var(--accent-light),#818cf8);border-radius:5px;transition:width .2s ease;"></div>
            </div>
            <div id="fm-upload-percent" style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:6px;">0%</div>
            <p id="fm-upload-status" style="font-size:12px;color:var(--text-muted);margin:0;">Subiendo a Livewire, por favor espera...</p>
        </div>
    </div>

    <script>
        (function () {
            if (window.__fmUploadBound) return;
            window.__fmUploadBound = true;

            function el(id) { return document.getElementById(id); }

            function formatSize(bytes) {
                if (!bytes && bytes !== 0) return '';
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
                return (bytes / 1073741824).toFixed(2) + ' GB';
            }

            function setProgress(p) {
                p = Math.max(0, Math.min(100, Math.round(p)));
                var bar = el('fm-upload-bar');
                var pct = el('fm-upload-percent');
                if (bar) bar.style.width = p + '%';
                if (pct) pct.textContent = p + '%';
            }

            function showModal() {
                var modal = el('fm-upload-modal');
                if (!modal) return;
                modal.style.display = 'flex';
                setProgress(0);
            }

            function hideModal() {
                var modal = el('fm-upload-modal');
                if (!modal) return;
                modal.style.display = 'none';
                var status = el('fm-upload-status');
                if (status) status.textContent = 'Subiendo a Livewire, por favor espera...';
            }

            // Captura los nombres/tamaños en el change ANTES de que Livewire consuma el input
            document.addEventListener('change', function (e) {
                if (e.target && e.target.tagName === 'INPUT' && e.target.type === 'file') {
                    var files = e.target.files;
                    var nameEl = el('fm-upload-filename');
                    var total = 0;
                    if (files && files.length) {
                        for (var i = 0; i < files.length; i++) total += files[i].size;
                        var label = files.length === 1
                            ? files[0].name + ' (' + formatSize(total) + ')'
                            : files.length + ' archivos (' + formatSize(total) + ')';
                        if (nameEl) nameEl.innerHTML = '<span style="color:var(--text-muted);">Archivo(s):</span> ' + label;
                    } else {
                        if (nameEl) nameEl.innerHTML = '<span style="color:var(--text-muted);">Preparando...</span>';
                    }
                }
            }, true);

            window.addEventListener('livewire-upload-start', function () {
                showModal();
            });

            window.addEventListener('livewire-upload-progress', function (e) {
                if (e.detail && typeof e.detail.progress === 'number') setProgress(e.detail.progress);
            });

            window.addEventListener('livewire-upload-finish', function () {
                setProgress(100);
                var status = el('fm-upload-status');
                if (status) status.textContent = 'Completado';
                setTimeout(hideModal, 800);
            });

            window.addEventListener('livewire-upload-error', function () {
                var status = el('fm-upload-status');
                if (status) status.textContent = 'Error al subir el archivo';
                setTimeout(hideModal, 3000);
            });

            window.addEventListener('livewire-upload-cancel', hideModal);
        })();
    </script>

    {{-- Unzip Progress Modal --}}
    @if($showUnzipModal)
    <div class="fm-modal-backdrop" style="z-index:9999;">
        <div class="glass-elevated fm-modal" style="max-width:500px;display:flex;flex-direction:column;"
             x-data="{ isExtracting: false }">
            
            <div style="text-align:center;margin-bottom:16px;">
                <i class="fa-solid fa-box-open" style="font-size:48px;color:var(--success);margin-bottom:16px;"></i>
                <h3 style="font-size:18px;font-weight:700;margin-bottom:4px;">Extrayendo Archivos</h3>
                <p style="font-size:13px;color:var(--text-muted);">{{ $unzipItemName }}</p>
            </div>

            <div x-show="!isExtracting" style="display:flex;justify-content:center;gap:12px;margin-bottom:20px;">
                <button wire:click="$set('showUnzipModal', false)" class="btn btn-ghost">Cancelar</button>
                <button @click="isExtracting = true; $wire.processUnzip()" class="btn btn-primary" style="background:var(--success);color:black;border:none;font-weight:700;">
                    <i class="fa-solid fa-play"></i> Iniciar Descompresión
                </button>
            </div>

            <div x-show="isExtracting" style="display:none;" x-bind:style="isExtracting ? 'display:block;' : 'display:none;'">
                <div style="width:100%;height:8px;background:rgba(255,255,255,0.1);border-radius:4px;overflow:hidden;margin-bottom:12px;">
                    <div wire:stream="unzip-progress" style="width: 0%" class="bg-blue-500 h-full rounded-full transition-all duration-300"></div>
                </div>
                
                <div style="font-size:14px;font-weight:600;color:var(--text-secondary);text-align:center;margin-bottom:16px;">
                    <span wire:stream="unzip-percentage">0%</span> Completado
                </div>

                <div class="fm-unzip-log" id="unzip-log-container">
                    <div wire:stream="unzip-log">
                        <div class="text-xs text-gray-500 italic">Esperando inicio...</div>
                    </div>
                </div>
            </div>
            
            <script>
                const logContainer = document.getElementById('unzip-log-container');
                if (logContainer) {
                    const observer = new MutationObserver(() => {
                        logContainer.scrollTop = logContainer.scrollHeight;
                    });
                    observer.observe(logContainer, { childList: true, subtree: true });
                }
            </script>
        </div>
    </div>
    @endif
</div>
<style>
.fm-container {
    display: flex; height: calc(100vh - 140px); gap: 20px;
    font-family: 'Outfit', sans-serif; color: var(--text-primary);
}
.fm-sidebar {
    width: 280px; display: flex; flex-direction: column; padding: 0;
    border-right: 1px solid var(--glass-border); background: rgba(10, 15, 30, 0.4);
    flex-shrink: 0;
}
.fm-main {
    flex: 1; display: flex; flex-direction: column; padding: 0;
    overflow: hidden; background: rgba(10, 15, 30, 0.2);
    min-width: 0;
}

/* Sidebar */
.fm-sidebar-header { padding: 24px 20px; border-bottom: 1px solid var(--glass-border); }
.fm-sidebar-header h3 { font-size: 16px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 10px; }
.fm-sidebar-content { flex: 1; overflow-y: auto; padding: 16px 12px; display: flex; flex-direction: column; gap: 10px; }
.fm-sidebar-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; font-weight: 800; margin: 0 12px 10px; }
.fm-storage-widget { margin-top: auto; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); border-radius: 12px; padding: 16px; }
.fm-storage-bar { height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; margin-bottom: 8px; }
.fm-storage-fill { height: 100%; background: linear-gradient(90deg, var(--accent-light), #818cf8); border-radius: 3px; }

/* Toolbar */
.fm-toolbar { padding: 16px 24px; border-bottom: 1px solid var(--glass-border); display: flex; align-items: center; justify-content: space-between; gap: 20px; background: rgba(255,255,255,0.01); }
.fm-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 14px; overflow-x: auto; white-space: nowrap; flex: 1; }
.fm-toolbar-actions { display: flex; align-items: center; gap: 8px; }

/* Alerts */
.fm-alert { padding: 12px 24px; font-size: 13px; border-bottom: 1px solid; display: flex; align-items: center; gap: 10px; }
.fm-alert-success { background: rgba(34,197,94,0.12); color: #4ade80; border-color: rgba(34,197,94,0.2); }
.fm-alert-error { background: rgba(239,68,68,0.12); color: #f87171; border-color: rgba(239,68,68,0.2); }

/* Table */
.fm-table { width: 100%; margin: 0; border-collapse: collapse; font-size: 13px; }
.fm-table thead { position: sticky; top: 0; background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(12px); z-index: 10; border-bottom: 1px solid var(--glass-border); }
.fm-table th { padding: 14px 10px; text-align: left; font-weight: 700; color: var(--text-muted); }
.fm-table td { padding: 12px 10px; vertical-align: middle; color: var(--text-secondary); }
.fm-table td.fm-name-col { padding: 12px 10px; }
.fm-table td.fm-actions { text-align: right; padding-right: 24px; }
.fm-table tr.fm-row { border-bottom: 1px solid rgba(255,255,255,0.03); transition: background 0.2s; cursor: default; }
.fm-table tr.fm-row:hover { background: rgba(255, 255, 255, 0.02) !important; }
.fm-table tr.fm-row.fm-selected { background: rgba(99, 102, 241, 0.05); }
.fm-drop-target { outline: 2px dashed var(--accent-light); outline-offset: -2px; background: rgba(99, 102, 241, 0.10) !important; }

/* Action buttons */
.fm-actions-bar { display: inline-flex; gap: 4px; }

/* Floating action bar */
.fm-float-bar { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 90; display: flex; align-items: center; gap: 16px; background: rgba(15, 23, 42, 0.95); border: 1px solid var(--accent-light); box-shadow: 0 10px 30px rgba(0,0,0,0.5); border-radius: 14px; padding: 12px 24px; backdrop-filter: blur(16px); animation: slideUp 0.3s ease-out; }
.fm-float-count { width: 20px; height: 20px; border-radius: 50%; background: var(--accent-light); color: black; display: flex; align-items: center; justify-content: center; font-size: 11px; }
.fm-float-divider { width: 1px; height: 24px; background: var(--glass-border); }

/* Modals */
.fm-modal-backdrop { position: fixed; inset: 0; z-index: 200; background: rgba(0,0,0,0.8); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content:center; animation: fmBackdropIn 180ms ease-out; }
.fm-modal { width: 100%; max-width: 380px; padding: 28px; border-radius: 16px; border: 1px solid var(--glass-border); background: rgba(15,23,42,0.95); animation: fmModalIn 220ms ease-out; transform-origin: center; }
.fm-modal-lg { max-width: 420px; }
.fm-modal-title { font-size: 18px; font-weight: 700; margin: 0 0 16px; display: flex; align-items: center; gap: 10px; }
.fm-modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 24px; }
.fm-modal-label { font-size: 12px; color: var(--text-muted); display: block; margin-bottom: 8px; }
@keyframes fmBackdropIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes fmModalIn { from { opacity: 0; transform: scale(0.96); } to { opacity: 1; transform: scale(1); } }

/* Delete modal */
.fm-delete-icon { width: 52px; height: 52px; border-radius: 50%; background: rgba(239,68,68,0.15); border: 2px solid rgba(239,68,68,0.3); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
.fm-delete-text { color: var(--text-secondary); font-size: 13px; margin-bottom: 24px; }

/* Editor */
.fm-editor-overlay { position: fixed; inset: 0; z-index: 300; background: rgba(8,11,20,0.99); display: flex; flex-direction: column; backdrop-filter: blur(12px); }
.fm-editor-header { background: rgba(255,255,255,0.02); padding: 14px 28px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: space-between; }
.fm-editor-icon { width: 40px; height: 40px; background: rgba(99,102,241,0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(99,102,241,0.25); }
.fm-editor-info strong { font-size: 15px; color: var(--text-primary); font-family: monospace; letter-spacing: 0.5px; }
.fm-editor-info div { font-size: 11px; color: var(--text-muted); font-family: monospace; margin-top: 2px; }
.fm-editor-actions { display: flex; align-items: center; gap: 18px; }
.fm-editor-status { font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 8px; }
.fm-editor-save { height: 36px; background: var(--accent-light); border: none; color: black; font-weight: 700; border-radius: 8px; padding: 0 18px; display: flex; align-items: center; gap: 8px; cursor: pointer; transition: transform 0.1s; }
.fm-editor-close { height: 36px; background: rgba(255,255,255,0.05); border-radius: 8px; padding: 0 16px; color: white; border: 1px solid var(--glass-border); }

/* Upload modal */
.fm-upload-progress { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 9999; align-items: center; justify-content: center; }
.fm-upload-card { background: rgba(15, 23, 42, 0.95); border: 1px solid var(--glass-border); border-radius: 12px; padding: 32px; width: 100%; max-width: 400px; text-align: center; }
.fm-upload-bar { width: 100%; height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden; margin-bottom: 12px; }
.fm-upload-fill { height: 100%; background: var(--accent-light); border-radius: 4px; transition: width 0.3s; }

/* Drop zone overlay */
.fm-dropzone-overlay { position: absolute; inset: 0; z-index: 50; background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; pointer-events: none; border: 3px dashed var(--accent-light); border-radius: 12px; margin: 8px; }
.fm-dropzone-card { text-align: center; padding: 48px; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 16px; }
.fm-dropzone-card h3 { color: var(--text-primary); }
.fm-dropzone-card p { color: var(--text-muted); }
.fm-dropzone-card strong { color: var(--accent-light); }
@keyframes fmUploadPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }

/* Unzip modal */
.fm-unzip-log { background: rgba(0,0,0,0.3); border: 1px solid var(--glass-border); border-radius: 8px; padding: 12px; height: 120px; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; font-family: monospace; }

/* Context menu (right click) */
[x-cloak] { display: none !important; }
.fm-ctx-menu { position: fixed; z-index: 1000; min-width: 230px; background: rgba(15, 23, 42, 0.97); border: 1px solid var(--glass-border); border-radius: 10px; padding: 6px; box-shadow: 0 12px 32px rgba(0,0,0,0.55); backdrop-filter: blur(14px); animation: fmCtxIn 120ms ease-out; transform-origin: top left; }
@keyframes fmCtxIn { from { opacity: 0; transform: scale(0.96); } to { opacity: 1; transform: scale(1); } }
.fm-ctx-header { display: flex; align-items: center; gap: 8px; padding: 8px 12px 8px; font-size: 11px; font-weight: 700; color: var(--text-muted); border-bottom: 1px solid var(--glass-border); margin-bottom: 4px; }
.fm-ctx-header span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px; font-family: monospace; }
.fm-ctx-item { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; padding: 8px 12px; border: none; background: transparent; color: var(--text-primary); font-size: 13px; font-weight: 500; border-radius: 7px; cursor: pointer; transition: background 0.15s, color 0.15s; }
.fm-ctx-item:hover { background: rgba(99, 102, 241, 0.18); color: white; }
.fm-ctx-danger { color: #f87171; }
.fm-ctx-danger:hover { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
.fm-ctx-divider { height: 1px; background: var(--glass-border); margin: 5px 8px; }

/* Favorite star in file rows */
.fm-star-btn { opacity: 0; transition: opacity 0.15s, color 0.15s, transform 0.15s; background: transparent; border: none; cursor: pointer; padding: 2px 5px; color: var(--text-muted); line-height: 1; }
tr.fm-row:hover .fm-star-btn { opacity: 0.6; }
.fm-star-btn:hover { opacity: 1 !important; color: #fbbf24; transform: scale(1.15); }
.fm-star-btn.fm-fav { opacity: 1; color: #fbbf24; }

@media (max-width: 768px) {
    .fm-container {
        flex-direction: column;
        height: auto;
        min-height: calc(100vh - 140px);
    }
    .fm-sidebar {
        width: 100%;
        height: 350px;
        border-right: none;
        border-bottom: 1px solid var(--glass-border);
    }
    .fm-main {
        overflow: visible;
    }
    .fm-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    .fm-toolbar-actions {
        flex-wrap: wrap;
    }
    .fm-toolbar-actions .btn {
        flex: 1;
        justify-content: center;
    }
}

@keyframes slideUp {
    from { transform: translate(-50%, 50px); opacity: 0; }
    to { transform: translate(-50%, 0); opacity: 1; }
}
</style>
