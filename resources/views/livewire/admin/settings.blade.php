<div style="font-family:'Outfit', sans-serif;color:var(--text-primary);" @if($isUpdating) wire:poll.1s="pollUpdateStatus" @endif>
    {{-- Main Container --}}
    <div style="display:flex;flex-direction:column;gap:24px;">
        
        {{-- Navigation Tabs --}}
        <div style="display:flex;gap:12px;border-bottom:1px solid var(--glass-border);padding-bottom:2px;">
            <button wire:click="$set('activeTab', 'updates')" class="btn-tab" style="padding:12px 20px;font-size:14px;font-weight:700;border:none;background:transparent;cursor:pointer;border-bottom:2px solid {{ $activeTab === 'updates' ? 'var(--accent-light)' : 'transparent' }};color:{{ $activeTab === 'updates' ? 'var(--accent-light)' : 'var(--text-secondary)' }};transition:all 0.2s;">
                <i class="fa-solid fa-cloud-arrow-down" style="margin-right:8px;"></i>Actualizaciones
            </button>
            <button wire:click="$set('activeTab', 'general')" class="btn-tab" style="padding:12px 20px;font-size:14px;font-weight:700;border:none;background:transparent;cursor:pointer;border-bottom:2px solid {{ $activeTab === 'general' ? 'var(--accent-light)' : 'transparent' }};color:{{ $activeTab === 'general' ? 'var(--text-secondary)' : 'transparent' }};color:{{ $activeTab === 'general' ? 'var(--text-primary)' : 'var(--text-secondary)' }};transition:all 0.2s;">
                <i class="fa-solid fa-sliders" style="margin-right:8px;"></i>Ajustes Generales
            </button>
        </div>

        {{-- Tab Content: Updates --}}
        @if($activeTab === 'updates')
        <div style="display:flex;flex-direction:column;gap:24px;">
            
            {{-- Alerts --}}
            @if($successMessage)
            <div class="glass" style="padding:16px 24px;background:rgba(34,197,94,0.12);color:#4ade80;font-size:14px;border:1px solid rgba(34,197,94,0.2);border-radius:12px;display:flex;align-items:center;gap:12px;">
                <i class="fa-solid fa-circle-check" style="font-size:20px;"></i> 
                <span>{{ $successMessage }}</span>
            </div>
            @endif

            @if($errorMessage)
            <div class="glass" style="padding:16px 24px;background:rgba(239,68,68,0.12);color:#f87171;font-size:14px;border:1px solid rgba(239,68,68,0.2);border-radius:12px;display:flex;align-items:center;gap:12px;">
                <i class="fa-solid fa-circle-exclamation" style="font-size:20px;"></i> 
                <span>{{ $errorMessage }}</span>
            </div>
            @endif

            {{-- Status Banner --}}
            <div class="glass" style="padding:32px;border-radius:16px;background:rgba(255,255,255,0.01);display:flex;align-items:center;justify-content:between;gap:30px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:20px;">
                    @if($isUpdateAvailable)
                        <div style="width:64px;height:64px;background:rgba(245,158,11,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid rgba(245,158,11,0.25);">
                            <i class="fa-solid fa-bell-exclamation" style="font-size:28px;color:#fbbf24;animation:pulse 2s infinite;"></i>
                        </div>
                        <div>
                            <h3 style="font-size:20px;font-weight:700;margin:0 0 6px;">¡Actualización Disponible!</h3>
                            <p style="font-size:13px;color:var(--text-secondary);margin:0;">Hay nuevas características y mejoras listas para descargar en tu panel.</p>
                        </div>
                    @else
                        <div style="width:64px;height:64px;background:rgba(16,185,129,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid rgba(16,185,129,0.25);">
                            <i class="fa-solid fa-circle-check" style="font-size:28px;color:#34d399;"></i>
                        </div>
                        <div>
                            <h3 style="font-size:20px;font-weight:700;margin:0 0 6px;">LaraPanel está al día</h3>
                            <p style="font-size:13px;color:var(--text-secondary);margin:0;">Tienes instalada la última versión disponible del sistema.</p>
                        </div>
                    @endif
                </div>

                <div style="display:flex;gap:12px;align-items:center;">
                    <button wire:click="checkForUpdates" class="btn btn-ghost" style="padding:10px 20px;border-radius:10px;font-size:13px;font-weight:600;background:rgba(255,255,255,0.03);display:flex;align-items:center;gap:8px;" wire:loading.attr="disabled">
                        <i class="fa-solid fa-rotate" wire:loading.class="fa-spin"></i>
                        <span>Buscar de nuevo</span>
                    </button>

                    @if($isUpdateAvailable && !$isUpdating)
                    <button wire:click="startUpdate" class="btn btn-primary" style="padding:10px 24px;border-radius:10px;font-size:13px;font-weight:700;background:var(--accent-light);color:black;border:none;display:flex;align-items:center;gap:8px;box-shadow:0 4px 15px rgba(99,102,241,0.2);">
                        <i class="fa-solid fa-download"></i>
                        <span>Actualizar LaraPanel</span>
                    </button>
                    @endif
                </div>
            </div>

            {{-- Commits & Versions Grid --}}
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:24px;">
                
                {{-- Local Info --}}
                <div class="glass" style="padding:24px;border-radius:16px;background:rgba(255,255,255,0.01);">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1.5px;font-weight:800;margin-bottom:16px;">Versión Local Instalada</div>
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.03);padding-bottom:10px;">
                            <span style="color:var(--text-secondary);">Versión:</span>
                            <span style="font-weight:700;color:var(--accent-light);">v{{ config('larapanel.version') }}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.03);padding-bottom:10px;">
                            <span style="color:var(--text-secondary);">Commit actual:</span>
                            <span style="font-family:monospace;background:rgba(255,255,255,0.05);padding:3px 8px;border-radius:6px;font-size:11px;color:var(--text-primary);">{{ substr($currentCommitHash, 0, 7) }}</span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Mensaje de commit local:</span>
                            <p style="font-family:monospace;font-size:12px;margin:0;color:var(--text-secondary);background:rgba(0,0,0,0.2);padding:10px;border-radius:8px;white-space:pre-wrap;">{{ $currentCommitMessage ?: 'Sin detalles.' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Upstream Remote Info --}}
                <div class="glass" style="padding:24px;border-radius:16px;background:rgba(255,255,255,0.01);">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1.5px;font-weight:800;margin-bottom:16px;">Última Versión en GitHub</div>
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.03);padding-bottom:10px;">
                            <span style="color:var(--text-secondary);">Repuesto remoto:</span>
                            <span style="font-weight:600;color:var(--text-primary);">GitHub (origin)</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.03);padding-bottom:10px;">
                            <span style="color:var(--text-secondary);">Último hash:</span>
                            <span style="font-family:monospace;background:rgba(255,255,255,0.05);padding:3px 8px;border-radius:6px;font-size:11px;color:{{ $isUpdateAvailable ? '#fbbf24' : 'var(--text-primary)' }};">{{ substr($latestCommitHash, 0, 7) ?: 'N/D' }}</span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:4px;">Último mensaje remoto:</span>
                            <p style="font-family:monospace;font-size:12px;margin:0;color:var(--text-secondary);background:rgba(0,0,0,0.2);padding:10px;border-radius:8px;white-space:pre-wrap;">{{ $latestCommitMessage ?: 'Sin detalles remotos.' }}</p>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Pending Commits List --}}
            @if($isUpdateAvailable && !empty($pendingCommits))
            <div class="glass" style="padding:28px;border-radius:16px;background:rgba(255,255,255,0.01);">
                <h4 style="font-size:15px;font-weight:700;margin:0 0 16px;display:flex;align-items:center;gap:10px;">
                    <i class="fa-solid fa-list-check" style="color:var(--accent-light);"></i>
                    <span>Historial de cambios pendientes</span>
                </h4>
                <div style="display:flex;flex-direction:column;gap:10px;max-height:220px;overflow-y:auto;padding-right:10px;">
                    @foreach($pendingCommits as $commit)
                        <div style="display:flex;align-items:flex-start;gap:12px;font-family:monospace;font-size:12px;padding:8px 12px;background:rgba(255,255,255,0.02);border-radius:8px;border:1px solid rgba(255,255,255,0.02);">
                            <span style="color:var(--accent-light);font-weight:700;">{{ substr($commit, 0, 7) }}</span>
                            <span style="color:var(--text-secondary);">{{ substr($commit, 8) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Terminal Execution Logs --}}
            @if($updateStatus !== 'idle')
            <div class="glass" style="padding:28px;border-radius:16px;border:1px solid {{ $updateStatus === 'failed' ? 'rgba(239,68,68,0.25)' : ($updateStatus === 'success' ? 'rgba(34,197,94,0.25)' : 'var(--accent-light)') }};background:#0f172a;box-shadow:0 10px 30px rgba(0,0,0,0.5);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h4 style="font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:10px;">
                        <i class="fa-solid fa-terminal" style="color:{{ $updateStatus === 'failed' ? '#f87171' : ($updateStatus === 'success' ? '#4ade80' : 'var(--accent-light)') }};"></i>
                        <span>Terminal — Log de actualización</span>
                    </h4>
                    
                    <div style="display:flex;align-items:center;gap:10px;">
                        @if($updateStatus === 'running')
                            <i class="fa-solid fa-spinner fa-spin" style="color:var(--warning);"></i>
                            <span style="font-size:12px;color:var(--warning);font-weight:600;">Ejecutando en segundo plano...</span>
                        @elseif($updateStatus === 'success')
                            <i class="fa-solid fa-circle-check" style="color:#4ade80;"></i>
                            <span style="font-size:12px;color:#4ade80;font-weight:600;">Completado con éxito</span>
                        @elseif($updateStatus === 'failed')
                            <i class="fa-solid fa-circle-xmark" style="color:#f87171;"></i>
                            <span style="font-size:12px;color:#f87171;font-weight:600;">Error en la ejecución</span>
                        @endif
                    </div>
                </div>

                {{-- Fake Console Output Container --}}
                <div id="update-terminal-body" style="height:260px;background:#090d16;border:1px solid rgba(255,255,255,0.05);border-radius:10px;padding:16px;overflow-y:auto;font-family:'Fira Code', 'Courier New', monospace;font-size:12px;color:#e2e8f0;line-height:1.6;white-space:pre-wrap;scroll-behavior:smooth;">
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
        <div class="glass" style="padding:40px;border-radius:16px;background:rgba(255,255,255,0.01);text-align:center;">
            <div style="width:72px;height:72px;background:rgba(99,102,241,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:1px solid rgba(99,102,241,0.2);">
                <i class="fa-solid fa-sliders" style="font-size:32px;color:var(--accent-light);"></i>
            </div>
            <h3 style="font-size:20px;font-weight:700;margin:0 0 8px;">Ajustes de Configuración General</h3>
            <p style="font-size:13px;color:var(--text-secondary);max-width:460px;margin:0 auto 24px;">Configura las alertas de recursos, rutas predeterminadas, copias de seguridad de LaraPanel y exclusiones de seguridad de tu panel.</p>
            <div style="font-size:12px;color:var(--text-muted);font-style:italic;">Características próximamente en la versión estable v0.2.0.</div>
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
