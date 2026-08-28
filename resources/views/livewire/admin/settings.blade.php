<div style="color:var(--text-primary);" @if($isUpdating) wire:poll.1s="pollUpdateStatus" @endif>
    {{-- Main Container --}}
    <div style="display:flex;flex-direction:column;gap:var(--sp-6);">
        
        {{-- Navigation Tabs --}}
        <div style="display:flex;gap:var(--sp-3);border-bottom:1px solid var(--glass-border);padding-bottom:var(--sp-1);">
            <button wire:click="$set('activeTab', 'updates')" class="btn-tab" style="padding:var(--sp-3) var(--sp-6);font-size:var(--text-base);font-weight:700;border:none;background:transparent;cursor:pointer;border-bottom:2px solid {{ $activeTab === 'updates' ? 'var(--accent-light)' : 'transparent' }};color:{{ $activeTab === 'updates' ? 'var(--accent-light)' : 'var(--text-secondary)' }};transition:all var(--transition);">
                <i class="fa-solid fa-cloud-arrow-down" style="margin-right:var(--sp-2);"></i>Actualizaciones
            </button>
            <button wire:click="$set('activeTab', 'general')" class="btn-tab" style="padding:var(--sp-3) var(--sp-6);font-size:var(--text-base);font-weight:700;border:none;background:transparent;cursor:pointer;border-bottom:2px solid {{ $activeTab === 'general' ? 'var(--accent-light)' : 'transparent' }};color:{{ $activeTab === 'general' ? 'var(--text-secondary)' : 'transparent' }};color:{{ $activeTab === 'general' ? 'var(--text-primary)' : 'var(--text-secondary)' }};transition:all var(--transition);">
                <i class="fa-solid fa-sliders" style="margin-right:var(--sp-2);"></i>Ajustes Generales
            </button>
        </div>

        {{-- Tab Content: Updates --}}
        @if($activeTab === 'updates')
        <div style="display:flex;flex-direction:column;gap:var(--sp-6);">
            
            {{-- Alerts --}}
            @if($successMessage)
            <div class="glass" style="padding:var(--sp-4) var(--sp-6);background:rgba(16,185,129,0.12);color:var(--success);font-size:var(--text-base);border:1px solid rgba(16,185,129,0.2);border-radius:var(--radius);display:flex;align-items:center;gap:var(--sp-3);">
                <i class="fa-solid fa-circle-check" style="font-size:var(--text-xl);"></i> 
                <span>{{ $successMessage }}</span>
            </div>
            @endif

            @if($errorMessage)
            <div class="glass" style="padding:var(--sp-4) var(--sp-6);background:rgba(239,68,68,0.12);color:var(--danger);font-size:var(--text-base);border:1px solid rgba(239,68,68,0.2);border-radius:var(--radius);display:flex;align-items:center;gap:var(--sp-3);">
                <i class="fa-solid fa-circle-exclamation" style="font-size:var(--text-xl);"></i> 
                <span>{{ $errorMessage }}</span>
            </div>
            @endif

            @if($workingTreeDirty && $isUpdateAvailable)
            <div class="glass" style="padding:var(--sp-3) var(--sp-4);background:rgba(245,158,11,0.12);color:var(--warning);font-size:var(--text-sm);border:1px solid rgba(245,158,11,0.25);border-radius:var(--radius);display:flex;align-items:center;gap:var(--sp-3);">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Hay cambios locales sin confirmar. El actualizador ejecuta <code>git reset --hard</code> y puede sobrescribirlos.</span>
            </div>
            @endif

            {{-- Status Banner --}}
            <div class="glass" style="padding:var(--sp-8);border-radius:var(--radius-lg);background:rgba(255,255,255,0.01);display:flex;align-items:center;justify-content:space-between;gap:var(--sp-8);flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:var(--sp-6);">
                    @if($isUpdateAvailable)
                        <div style="width:64px;height:64px;background:rgba(245,158,11,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid rgba(245,158,11,0.25);">
                            <i class="fa-solid fa-bell-exclamation" style="font-size:var(--text-2xl);color:var(--warning);animation:pulse 2s infinite;"></i>
                        </div>
                        <div>
                            <h3 style="font-size:var(--text-xl);font-weight:700;margin:0 0 var(--sp-1);">¡Actualización Disponible!</h3>
                            <p style="font-size:var(--text-sm);color:var(--text-secondary);margin:0;">Hay cambios disponibles en el repositorio remoto para instalar en tu panel.</p>
                        </div>
                    @else
                        <div style="width:64px;height:64px;background:rgba(16,185,129,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid rgba(16,185,129,0.25);">
                            <i class="fa-solid fa-circle-check" style="font-size:var(--text-2xl);color:var(--success);"></i>
                        </div>
                        <div>
                            <h3 style="font-size:var(--text-xl);font-weight:700;margin:0 0 var(--sp-1);">LaraPanel está al día</h3>
                            <p style="font-size:var(--text-sm);color:var(--text-secondary);margin:0;">El commit instalado coincide con el commit remoto consultado.</p>
                        </div>
                    @endif
                </div>

                <div style="display:flex;gap:var(--sp-3);align-items:center;">
                    <button wire:click="checkForUpdates" class="btn btn-ghost" style="padding:var(--sp-2) var(--sp-4);border-radius:var(--radius-sm);font-size:var(--text-sm);font-weight:600;background:rgba(255,255,255,0.03);display:flex;align-items:center;gap:var(--sp-2);" wire:loading.attr="disabled">
                        <i class="fa-solid fa-rotate" wire:loading.class="fa-spin"></i>
                        <span>Buscar de nuevo</span>
                    </button>

                    @if($isUpdateAvailable && !$isUpdating)
                    <button wire:click="startUpdate" class="btn btn-primary" style="padding:var(--sp-2) var(--sp-6);border-radius:var(--radius-sm);font-size:var(--text-sm);font-weight:700;background:var(--accent-light);color:black;border:none;display:flex;align-items:center;gap:var(--sp-2);box-shadow:var(--shadow-md);">
                        <i class="fa-solid fa-download"></i>
                        <span>Actualizar LaraPanel</span>
                    </button>
                    @endif
                </div>
            </div>

            {{-- Commits & Versions Grid --}}
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:var(--sp-6);">
                
                {{-- Local Info --}}
                <div class="glass" style="padding:var(--sp-6);border-radius:var(--radius-lg);background:rgba(255,255,255,0.01);">
                    <div style="font-size:var(--text-xs);color:var(--text-muted);text-transform:uppercase;letter-spacing:1.5px;font-weight:800;margin-bottom:var(--sp-4);">Versión Local Instalada</div>
                    <div style="display:flex;flex-direction:column;gap:var(--sp-3);">
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:var(--text-sm);border-bottom:1px solid rgba(255,255,255,0.03);padding-bottom:var(--sp-2);">
                            <span style="color:var(--text-secondary);">Versión:</span>
                            <span style="font-weight:700;color:var(--accent-light);">v{{ config('larapanel.version') }}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:var(--text-sm);border-bottom:1px solid rgba(255,255,255,0.03);padding-bottom:var(--sp-2);">
                            <span style="color:var(--text-secondary);">Commit actual:</span>
                            <span style="font-family:monospace;background:rgba(255,255,255,0.05);padding:var(--sp-1) var(--sp-2);border-radius:var(--radius-sm);font-size:var(--text-xs);color:var(--text-primary);">{{ substr($currentCommitHash, 0, 7) }}</span>
                        </div>
                        <div>
                            <span style="font-size:var(--text-xs);color:var(--text-muted);display:block;margin-bottom:var(--sp-1);">Mensaje de commit local:</span>
                            <p style="font-family:monospace;font-size:var(--text-sm);margin:0;color:var(--text-secondary);background:rgba(0,0,0,0.2);padding:var(--sp-2);border-radius:var(--radius-sm);white-space:pre-wrap;">{{ $currentCommitMessage ?: 'Sin detalles.' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Upstream Remote Info --}}
                <div class="glass" style="padding:var(--sp-6);border-radius:var(--radius-lg);background:rgba(255,255,255,0.01);">
                    <div style="font-size:var(--text-xs);color:var(--text-muted);text-transform:uppercase;letter-spacing:1.5px;font-weight:800;margin-bottom:var(--sp-4);">Última Versión en GitHub</div>
                    <div style="display:flex;flex-direction:column;gap:var(--sp-3);">
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:var(--text-sm);border-bottom:1px solid rgba(255,255,255,0.03);padding-bottom:var(--sp-2);">
                            <span style="color:var(--text-secondary);">Repositorio remoto:</span>
                            <span style="font-weight:600;color:var(--text-primary);">GitHub (origin)</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:var(--text-sm);border-bottom:1px solid rgba(255,255,255,0.03);padding-bottom:var(--sp-2);">
                            <span style="color:var(--text-secondary);">Último hash:</span>
                            <span style="font-family:monospace;background:rgba(255,255,255,0.05);padding:var(--sp-1) var(--sp-2);border-radius:var(--radius-sm);font-size:var(--text-xs);color:{{ $isUpdateAvailable ? 'var(--warning)' : 'var(--text-primary)' }};">{{ substr($latestCommitHash, 0, 7) ?: 'N/D' }}</span>
                        </div>
                        <div>
                            <span style="font-size:var(--text-xs);color:var(--text-muted);display:block;margin-bottom:var(--sp-1);">Último mensaje remoto:</span>
                            <p style="font-family:monospace;font-size:var(--text-sm);margin:0;color:var(--text-secondary);background:rgba(0,0,0,0.2);padding:var(--sp-2);border-radius:var(--radius-sm);white-space:pre-wrap;">{{ $latestCommitMessage ?: 'Sin detalles remotos.' }}</p>
                        </div>
                    </div>
                    @if($updateCheckedAt)
                        <div style="font-size:var(--text-xs);color:var(--text-muted);margin-top:var(--sp-3);">Consulta realizada: {{ $updateCheckedAt }}</div>
                    @endif
                </div>

            </div>

            {{-- Pending Commits List --}}
            @if($isUpdateAvailable && !empty($pendingCommits))
            <div class="glass" style="padding:var(--sp-8);border-radius:var(--radius-lg);background:rgba(255,255,255,0.01);">
                <h4 style="font-size:var(--text-base);font-weight:700;margin:0 0 var(--sp-4);display:flex;align-items:center;gap:var(--sp-2);">
                    <i class="fa-solid fa-list-check" style="color:var(--accent-light);"></i>
                    <span>Historial de cambios pendientes</span>
                </h4>
                <div style="display:flex;flex-direction:column;gap:var(--sp-2);max-height:220px;overflow-y:auto;padding-right:var(--sp-2);">
                    @foreach($pendingCommits as $commit)
                        <div style="display:flex;align-items:flex-start;gap:var(--sp-3);font-family:monospace;font-size:var(--text-sm);padding:var(--sp-2) var(--sp-3);background:rgba(255,255,255,0.02);border-radius:var(--radius-sm);border:1px solid rgba(255,255,255,0.02);">
                            <span style="color:var(--accent-light);font-weight:700;">{{ substr($commit, 0, 7) }}</span>
                            <span style="color:var(--text-secondary);">{{ substr($commit, 8) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Terminal Execution Logs --}}
            @if($updateStatus !== 'idle')
            <div class="glass" style="padding:var(--sp-8);border-radius:var(--radius-lg);border:1px solid {{ $updateStatus === 'failed' ? 'rgba(239,68,68,0.25)' : ($updateStatus === 'success' ? 'rgba(34,197,94,0.25)' : 'var(--accent-light)') }};background:var(--bg-surface);box-shadow:var(--shadow-lg);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--sp-4);">
                    <h4 style="font-size:var(--text-base);font-weight:700;margin:0;display:flex;align-items:center;gap:var(--sp-2);">
                        <i class="fa-solid fa-terminal" style="color:{{ $updateStatus === 'failed' ? 'var(--danger)' : ($updateStatus === 'success' ? 'var(--success)' : 'var(--accent-light)') }};"></i>
                        <span>Terminal — Log de actualización</span>
                    </h4>
                    
                    <div style="display:flex;align-items:center;gap:var(--sp-2);">
                        @if($updateStatus === 'running')
                            <i class="fa-solid fa-spinner fa-spin" style="color:var(--warning);"></i>
                            <span style="font-size:var(--text-sm);color:var(--warning);font-weight:600;">Ejecutando en segundo plano...</span>
                        @elseif($updateStatus === 'success')
                            <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                            <span style="font-size:var(--text-sm);color:var(--success);font-weight:600;">Completado con éxito</span>
                        @elseif($updateStatus === 'failed')
                            <i class="fa-solid fa-circle-xmark" style="color:var(--danger);"></i>
                            <span style="font-size:var(--text-sm);color:var(--danger);font-weight:600;">Error en la ejecución</span>
                        @endif
                    </div>
                </div>

                {{-- Fake Console Output Container --}}
                <div id="update-terminal-body" style="height:260px;background:var(--bg-base);border:1px solid var(--glass-border);border-radius:var(--radius-sm);padding:var(--sp-4);overflow-y:auto;font-family:'Fira Code', 'Courier New', monospace;font-size:var(--text-sm);color:var(--text-primary);line-height:1.6;white-space:pre-wrap;scroll-behavior:smooth;">
                    {{ $updateLog ?: 'Esperando salida del script...' }}
                </div>
            </div>
            
            <script>
                // Auto scroll console to bottom on updates
                document.addEventListener('livewire:initialized', () => {
                    const observer = new MutationObserver(() => {
                        const term = document.getElementById('update-terminal-body');
                        if (term) term.scrollTop = term.scrollHeight;
                    });
                    
                    const el = document.getElementById('update-terminal-body');
                    if (el) {
                        el.scrollTop = el.scrollHeight;
                        observer.observe(el, { childList: true, characterData: true, subtree: true });
                    }
                });
            </script>
            @endif

        </div>
        @endif

        {{-- Tab Content: General Settings (Placeholder style) --}}
        @if($activeTab === 'general')
        <div style="display:flex;flex-direction:column;gap:var(--sp-6);">

            {{-- Alerts --}}
            @if($generalSuccessMessage)
            <div class="glass" style="padding:var(--sp-4) var(--sp-6);background:rgba(16,185,129,0.12);color:var(--success);font-size:var(--text-base);border:1px solid rgba(16,185,129,0.2);border-radius:var(--radius);display:flex;align-items:center;gap:var(--sp-3);">
                <i class="fa-solid fa-circle-check" style="font-size:var(--text-xl);"></i> 
                <span>{{ $generalSuccessMessage }}</span>
            </div>
            @endif

            @if($generalErrorMessage)
            <div class="glass" style="padding:var(--sp-4) var(--sp-6);background:rgba(239,68,68,0.12);color:var(--danger);font-size:var(--text-base);border:1px solid rgba(239,68,68,0.2);border-radius:var(--radius);display:flex;align-items:center;gap:var(--sp-3);">
                <i class="fa-solid fa-circle-exclamation" style="font-size:var(--text-xl);"></i> 
                <span>{{ $generalErrorMessage }}</span>
            </div>
            @endif

            <form wire:submit.prevent="saveGeneralSettings" style="display:flex;flex-direction:column;gap:var(--sp-6);">
                
                {{-- Bloque 1: Monitoreo y Alertas --}}
                <div class="glass" style="padding:var(--sp-8);border-radius:var(--radius-lg);background:rgba(255,255,255,0.01);">
                    <h4 style="font-size:var(--text-lg);font-weight:700;margin:0 0 var(--sp-6);display:flex;align-items:center;gap:var(--sp-2);">
                        <div style="width:36px;height:36px;background:rgba(99,102,241,0.15);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;">
                            <i class="fa-solid fa-chart-line" style="color:var(--accent-light);"></i>
                        </div>
                        Sistema y Monitoreo
                    </h4>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-6);">
                        <div class="form-group" style="margin:0;">
                            <label style="display:flex;align-items:center;gap:var(--sp-2);cursor:pointer;">
                                <input type="checkbox" wire:model.defer="alertsEnabled" style="width:18px;height:18px;accent-color:var(--accent);">
                                <div>
                                    <span style="display:block;font-size:var(--text-sm);font-weight:600;color:var(--text-primary);">Activar Alertas de Recursos</span>
                                    <span style="font-size:var(--text-xs);color:var(--text-muted);">Recibir notificaciones si el servidor supera el umbral crítico.</span>
                                </div>
                            </label>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-4);">
                            <div class="form-group" style="margin:0;">
                                <label style="display:block;font-size:var(--text-sm);font-weight:600;margin-bottom:var(--sp-2);color:var(--text-secondary);">Umbral de Disco (%)</label>
                                <input type="number" wire:model.defer="diskThreshold" class="form-input" min="10" max="99">
                                @error('diskThreshold') <span style="color:var(--danger);font-size:var(--text-xs);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label style="display:block;font-size:var(--text-sm);font-weight:600;margin-bottom:var(--sp-2);color:var(--text-secondary);">Umbral de RAM (%)</label>
                                <input type="number" wire:model.defer="ramThreshold" class="form-input" min="10" max="99">
                                @error('ramThreshold') <span style="color:var(--danger);font-size:var(--text-xs);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bloque 2: Copias de Seguridad --}}
                <div class="glass" style="padding:var(--sp-8);border-radius:var(--radius-lg);background:rgba(255,255,255,0.01);">
                    <h4 style="font-size:var(--text-lg);font-weight:700;margin:0 0 var(--sp-6);display:flex;align-items:center;gap:var(--sp-2);">
                        <div style="width:36px;height:36px;background:rgba(16,185,129,0.15);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;">
                            <i class="fa-solid fa-server" style="color:var(--success);"></i>
                        </div>
                        Copias de Seguridad (Backups)
                    </h4>

                    <div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--sp-6);">
                        <div class="form-group" style="margin:0;">
                            <label style="display:block;font-size:var(--text-sm);font-weight:600;margin-bottom:var(--sp-2);color:var(--text-secondary);">Ruta predeterminada de almacenamiento</label>
                            <input type="text" wire:model.defer="backupPath" class="form-input" placeholder="/var/larapanel/backups">
                            <p style="font-size:var(--text-xs);color:var(--text-muted);margin-top:var(--sp-1);">Dónde se guardarán los archivos `.tar.gz` o volcados SQL.</p>
                            @error('backupPath') <span style="color:var(--danger);font-size:var(--text-xs);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="display:block;font-size:var(--text-sm);font-weight:600;margin-bottom:var(--sp-2);color:var(--text-secondary);">Días de Retención</label>
                            <div style="position:relative;">
                                <select wire:model.defer="backupRetention" class="form-input" style="appearance:none;">
                                    <option value="3">3 días</option>
                                    <option value="7">1 semana (7 días)</option>
                                    <option value="15">15 días</option>
                                    <option value="30">1 mes (30 días)</option>
                                </select>
                                <i class="fa-solid fa-chevron-down" style="position:absolute;right:var(--sp-3);top:var(--sp-3);color:var(--text-muted);font-size:var(--text-sm);pointer-events:none;z-index:2;"></i>
                            </div>
                            @error('backupRetention') <span style="color:var(--danger);font-size:var(--text-xs);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Bloque S3: Almacenamiento AWS S3 --}}
                <div class="glass" style="padding:var(--sp-8);border-radius:var(--radius-lg);background:rgba(255,255,255,0.01);">
                    <h4 style="font-size:var(--text-lg);font-weight:700;margin:0 0 var(--sp-6);display:flex;align-items:center;gap:var(--sp-2);">
                        <div style="width:36px;height:36px;background:rgba(251,191,36,0.15);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;">
                            <i class="fa-brands fa-aws" style="color:var(--warning);font-size:var(--text-xl);"></i>
                        </div>
                        Almacenamiento AWS S3
                    </h4>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:var(--sp-6);">
                        <div class="form-group" style="margin:0;">
                            <label style="display:block;font-size:var(--text-sm);font-weight:600;margin-bottom:var(--sp-2);color:var(--text-secondary);">AWS Access Key ID</label>
                            <input type="text" wire:model.defer="awsAccessKeyId" class="form-input" placeholder="Ej: AKIAIOSFODNN7EXAMPLE">
                            @error('awsAccessKeyId') <span style="color:var(--danger);font-size:var(--text-xs);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="form-group" style="margin:0;">
                            <label style="display:block;font-size:var(--text-sm);font-weight:600;margin-bottom:var(--sp-2);color:var(--text-secondary);">AWS Secret Access Key</label>
                            <input type="password" wire:model.defer="awsSecretAccessKey" class="form-input" placeholder="••••••••••••••••">
                            @error('awsSecretAccessKey') <span style="color:var(--danger);font-size:var(--text-xs);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label style="display:block;font-size:var(--text-sm);font-weight:600;margin-bottom:var(--sp-2);color:var(--text-secondary);">AWS Default Region</label>
                            <input type="text" wire:model.defer="awsDefaultRegion" class="form-input" placeholder="us-east-1">
                            @error('awsDefaultRegion') <span style="color:var(--danger);font-size:var(--text-xs);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label style="display:block;font-size:var(--text-sm);font-weight:600;margin-bottom:var(--sp-2);color:var(--text-secondary);">AWS Bucket</label>
                            <input type="text" wire:model.defer="awsBucket" class="form-input" placeholder="mi-bucket-backups">
                            @error('awsBucket') <span style="color:var(--danger);font-size:var(--text-xs);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1; margin:0;">
                            <label style="display:block;font-size:var(--text-sm);font-weight:600;margin-bottom:var(--sp-2);color:var(--text-secondary);">AWS Endpoint URL (Opcional)</label>
                            <input type="text" wire:model.defer="awsEndpoint" class="form-input" placeholder="https://s3.us-west-004.backblazeb2.com">
                            <p style="font-size:var(--text-xs);color:var(--text-muted);margin-top:var(--sp-1);">Vacío para AWS estándar. Rellena si usas MinIO, Wasabi, Backblaze B2, DigitalOcean Spaces, etc.</p>
                            @error('awsEndpoint') <span style="color:var(--danger);font-size:var(--text-xs);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Bloque: Notificaciones Telegram --}}
                <div class="glass" style="padding:var(--sp-8);border-radius:var(--radius-lg);background:rgba(255,255,255,0.01);">
                    <h4 style="font-size:var(--text-lg);font-weight:700;margin:0 0 var(--sp-6);display:flex;align-items:center;gap:var(--sp-2);">
                        <div style="width:36px;height:36px;background:rgba(0,136,204,0.15);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;">
                            <i class="fa-brands fa-telegram" style="color:#229ed9;font-size:var(--text-xl);"></i>
                        </div>
                        Notificaciones Telegram
                    </h4>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:var(--sp-6);">
                        <div class="form-group" style="grid-column:1 / -1;margin:0;display:flex;align-items:center;gap:var(--sp-2);">
                            <input type="checkbox" wire:model.defer="telegramEnabled" id="telegramEnabled" style="width:18px;height:18px;">
                            <label for="telegramEnabled" style="font-size:var(--text-sm);font-weight:600;color:var(--text-secondary);margin:0;">Habilitar notificaciones de Telegram</label>
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label style="display:block;font-size:var(--text-sm);font-weight:600;margin-bottom:var(--sp-2);color:var(--text-secondary);">Bot Token</label>
                            <input type="password" wire:model.defer="telegramBotToken" class="form-input" placeholder="123456789:AAE...">
                            @error('telegramBotToken') <span style="color:var(--danger);font-size:var(--text-xs);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label style="display:block;font-size:var(--text-sm);font-weight:600;margin-bottom:var(--sp-2);color:var(--text-secondary);">Chat ID</label>
                            <input type="text" wire:model.defer="telegramChatId" class="form-input" placeholder="-1001234567890">
                            @error('telegramChatId') <span style="color:var(--danger);font-size:var(--text-xs);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div style="display:flex;align-items:center;gap:var(--sp-3);margin-top:var(--sp-4);flex-wrap:wrap;">
                        <button type="button" wire:click="sendTestTelegram" wire:loading.attr="disabled" class="btn btn-ghost" style="padding:var(--sp-2) var(--sp-4);border-radius:var(--radius-sm);font-size:var(--text-sm);font-weight:600;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);color:var(--success);display:inline-flex;align-items:center;gap:var(--sp-2);cursor:pointer;">
                            <i class="fa-brands fa-telegram" wire:loading.class="fa-spin"></i>
                            <span>Enviar mensaje de prueba</span>
                        </button>
                        @if($telegramTestMessage)
                        <span style="font-size:var(--text-sm);{{ str_starts_with($telegramTestMessage, 'Error') ? 'color:var(--danger);' : 'color:var(--success);' }}">
                            <i class="fa-solid {{ str_starts_with($telegramTestMessage, 'Error') ? 'fa-circle-exclamation' : 'fa-circle-check' }}"></i>
                            {{ $telegramTestMessage }}
                        </span>
                        @endif
                    </div>

                    <p style="font-size:var(--text-xs);color:var(--text-muted);margin-top:var(--sp-3);">Recibe alertas de caídas de uptime, umbral de disco, fallos de backup e inicios de sesión sospechosos.</p>
                </div>

                {{-- Bloque 3: Localización y Sistema --}}
                <div class="glass" style="padding:var(--sp-8);border-radius:var(--radius-lg);background:rgba(255,255,255,0.01);">
                    <h4 style="font-size:var(--text-lg);font-weight:700;margin:0 0 var(--sp-6);display:flex;align-items:center;gap:var(--sp-2);">
                        <div style="width:36px;height:36px;background:rgba(245,158,11,0.15);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;">
                            <i class="fa-solid fa-earth-americas" style="color:var(--warning);"></i>
                        </div>
                        Localización
                    </h4>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-6);">
                        <div class="form-group" style="margin:0;">
                            <label style="display:block;font-size:var(--text-sm);font-weight:600;margin-bottom:var(--sp-2);color:var(--text-secondary);">Zona Horaria del Sistema</label>
                            <div style="position:relative;">
                                <select wire:model.defer="timezone" class="form-input" style="appearance:none;">
                                    <option value="UTC">UTC (Universal)</option>
                                    <option value="America/Argentina/Buenos_Aires">América / Buenos Aires</option>
                                    <option value="America/Santiago">América / Santiago</option>
                                    <option value="America/Bogota">América / Bogotá</option>
                                    <option value="America/Mexico_City">América / Ciudad de México</option>
                                    <option value="Europe/Madrid">Europa / Madrid</option>
                                </select>
                                <i class="fa-solid fa-chevron-down" style="position:absolute;right:var(--sp-3);top:var(--sp-3);color:var(--text-muted);font-size:var(--text-sm);pointer-events:none;z-index:2;"></i>
                            </div>
                            <p style="font-size:var(--text-xs);color:var(--text-muted);margin-top:var(--sp-1);">Afecta a los Cron Jobs y registros (logs).</p>
                            @error('timezone') <span style="color:var(--danger);font-size:var(--text-xs);margin-top:var(--sp-1);display:block;">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary" style="padding:var(--sp-2) var(--sp-6);border-radius:var(--radius-sm);font-size:var(--text-sm);font-weight:700;box-shadow:var(--shadow-md);">
                        <i class="fa-solid fa-save"></i>
                        <span>Guardar Ajustes</span>
                    </button>
                </div>
            </form>
        </div>
        @endif

    </div>
</div>

<style>
@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}
</style>
