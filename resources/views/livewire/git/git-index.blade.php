<div style="display:grid;grid-template-columns:260px 1fr;gap:24px;align-items:start;">
    {{-- Sidebar --}}
    <div class="glass" style="padding:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h2 style="font-size:14px;font-weight:700;">Repositorios</h2>
            <button wire:click="createNew" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i></button>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($deployments as $dep)
            <button wire:click="selectDeployment({{ $dep->id }})" 
                style="width:100%;text-align:left;padding:12px;border-radius:8px;border:1px solid {{ ($selectedDeployment && $selectedDeployment->id === $dep->id && !$isCreating) ? 'rgba(99,102,241,0.5)' : 'var(--glass-border)' }};background:{{ ($selectedDeployment && $selectedDeployment->id === $dep->id && !$isCreating) ? 'rgba(99,102,241,0.1)' : 'rgba(255,255,255,0.03)' }};cursor:pointer;transition:all 0.2s;">
                <div style="font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:4px;">{{ $dep->domain_name }}</div>
                <div style="font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:6px;">
                    <i class="fa-brands fa-git-alt"></i> {{ $dep->branch }}
                </div>
            </button>
            @endforeach

            @if($deployments->isEmpty())
            <div style="text-align:center;padding:20px 10px;color:var(--text-muted);font-size:12px;">
                No hay repositorios configurados.
            </div>
            @endif
        </div>
    </div>

    {{-- Main Content --}}
    <div>
        @if(session()->has('message'))
        <div class="alert alert-success" style="margin-bottom:20px;">
            <i class="fa-solid fa-circle-check"></i> {{ session('message') }}
        </div>
        @endif

        @if($isCreating)
            {{-- Create Form --}}
            <div class="glass" style="padding:24px;">
                <h2 style="font-size:18px;font-weight:700;margin-bottom:20px;">Configurar Nuevo Repositorio</h2>
                
                <form wire:submit.prevent="save">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div class="form-group">
                            <label class="form-label">Dominio / Proyecto</label>
                            <div style="position:relative;">
                                <select wire:model.live="domain_name" class="form-input" style="appearance:none;">
                                    <option value="">Selecciona un dominio...</option>
                                    @foreach($availableDomains as $domain)
                                        <option value="{{ $domain }}">{{ $domain }}</option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down" style="position:absolute;right:12px;top:12px;color:var(--text-muted);font-size:12px;pointer-events:none;"></i>
                            </div>
                            @error('domain_name') <span style="color:var(--danger);font-size:11px;">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">URL del Repositorio (HTTPS/SSH)</label>
                            <input type="text" wire:model="repository_url" class="form-input" placeholder="https://github.com/usuario/repo.git">
                            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                                Si es un repositorio privado, incluye el token: <br>
                                <code>https://usuario:TOKEN@gitlab.com/usuario/repo.git</code>
                            </div>
                            @error('repository_url') <span style="color:var(--danger);font-size:11px;">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label">Ruta de Instalación (Directorio del Repo)</label>
                        <input type="text" wire:model="deploy_path" class="form-input" placeholder="/var/www/ejemplo.com/public_html">
                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                            <i class="fa-solid fa-info-circle" style="color:var(--accent-light);"></i> 
                            Puedes especificar la ruta de un repositorio que <b>ya hayas descargado</b> manualmente en el servidor. Si existe, LaraPanel sólo lo actualizará sin clonarlo de nuevo.
                        </div>
                        @error('deploy_path') <span style="color:var(--danger);font-size:11px;">{{ $message }}</span> @enderror
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div class="form-group">
                            <label class="form-label">Rama a trackear (Branch)</label>
                            <input type="text" wire:model="branch" class="form-input" placeholder="main">
                            @error('branch') <span style="color:var(--danger);font-size:11px;">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group" style="display:flex;align-items:center;padding-top:28px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                                <input type="checkbox" wire:model="auto_deploy">
                                Activar Auto-Deploy (vía Webhook)
                            </label>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:24px;">
                        <label class="form-label">Script de Post-Despliegue (Bash)</label>
                        <textarea wire:model="deploy_script" class="form-input" rows="6" style="font-family:monospace;font-size:12px;line-height:1.6;"></textarea>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:6px;">Se ejecutará en la raíz del proyecto (<code>/var/www/dominio.com/public_html</code>) después de cada <code>git pull</code>.</div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:12px;">
                        <button type="button" wire:click="$set('isCreating', false)" class="btn btn-ghost">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar Configuración</button>
                    </div>
                </form>
            </div>
        @elseif($selectedDeployment)
            {{-- Tabs --}}
            <div style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;">
                <button wire:click="$set('activeTab', 'config')" class="btn {{ $activeTab === 'config' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
                    <i class="fa-solid fa-gear"></i> Configuración
                </button>
                <button wire:click="$set('activeTab', 'logs')" class="btn {{ $activeTab === 'logs' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
                    <i class="fa-solid fa-list-check"></i> Historial de Despliegues
                </button>
            </div>

            @if($activeTab === 'config')
                <div class="glass" style="padding:24px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                        <h2 style="font-size:18px;font-weight:700;">
                            <i class="fa-brands fa-git-alt" style="color:#f14e32;margin-right:8px;"></i>
                            {{ $selectedDeployment->domain_name }}
                        </h2>
                        <div style="display:flex;gap:8px;">
                            <button wire:click="deployNow" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                                <span wire:loading.remove><i class="fa-solid fa-rocket"></i> Desplegar Ahora</span>
                                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Desplegando...</span>
                            </button>
                            <button wire:click="delete" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar esta configuración? No se borrarán los archivos del servidor.')">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Repo Status Info --}}
                    @if(!empty($repoStatus))
                    <div style="background:rgba(255,255,255,0.02);border:1px solid var(--glass-border);border-radius:8px;padding:16px;margin-bottom:24px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <h3 style="font-size:13px;font-weight:700;color:var(--text-primary);"><i class="fa-brands fa-git-alt" style="color:#f14e32;margin-right:6px;"></i> Estado del Repositorio Local</h3>
                            <button wire:click="refreshRepoStatus" class="btn btn-ghost btn-sm" wire:loading.attr="disabled" wire:target="refreshRepoStatus">
                                <span wire:loading.remove wire:target="refreshRepoStatus"><i class="fa-solid fa-rotate"></i> Actualizar</span>
                                <span wire:loading wire:target="refreshRepoStatus"><i class="fa-solid fa-spinner fa-spin"></i></span>
                            </button>
                        </div>
                        
                        @if($repoStatus['status'] === 'not_found')
                            <span class="badge badge-warning">Directorio Inexistente</span>
                            <p style="color:var(--text-muted);margin-top:8px;font-size:12px;">{{ $repoStatus['message'] }}</p>
                        @elseif($repoStatus['status'] === 'not_initialized')
                            <span class="badge badge-warning">No Inicializado</span>
                            <p style="color:var(--text-muted);margin-top:8px;font-size:12px;">{{ $repoStatus['message'] }}</p>
                        @else
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:12px;">
                                <div>
                                    <div style="color:var(--text-muted);margin-bottom:4px;">Rama Actual</div>
                                    <div style="font-family:monospace;font-weight:600;"><i class="fa-solid fa-code-branch" style="color:var(--accent-light);margin-right:4px;"></i> {{ $repoStatus['branch'] }}</div>
                                </div>
                                <div>
                                    <div style="color:var(--text-muted);margin-bottom:4px;">Último Commit Descargado</div>
                                    <div style="font-family:monospace;font-size:11px;color:var(--text-primary);">{{ $repoStatus['commit'] }}</div>
                                </div>
                            </div>
                            @if($repoStatus['has_changes'])
                                <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--glass-border);">
                                    <div style="color:var(--warning);margin-bottom:6px;font-size:12px;font-weight:600;"><i class="fa-solid fa-triangle-exclamation"></i> Archivos modificados localmente (podrían sobreescribirse o causar conflictos):</div>
                                    <pre style="background:rgba(0,0,0,0.4);padding:10px;border-radius:6px;font-family:monospace;font-size:11px;color:var(--text-secondary);max-height:120px;overflow-y:auto;border:1px solid rgba(255,255,255,0.05);">{{ $repoStatus['changes'] }}</pre>
                                </div>
                            @else
                                <div style="margin-top:16px;color:var(--success);font-size:12px;display:flex;align-items:center;gap:6px;">
                                    <i class="fa-solid fa-circle-check"></i> Directorio de trabajo limpio
                                </div>
                            @endif
                        @endif
                    </div>
                    @endif

                    {{-- Webhook Info --}}
                    <div style="background:rgba(99,102,241,0.05);border:1px solid rgba(99,102,241,0.2);border-radius:8px;padding:16px;margin-bottom:24px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                            <h3 style="font-size:13px;font-weight:700;color:var(--accent-light);"><i class="fa-solid fa-link"></i> Webhook URL</h3>
                            @if($selectedDeployment->auto_deploy)
                                <span class="badge badge-success"><i class="fa-solid fa-bolt"></i> Auto-Deploy Activo</span>
                            @else
                                <span class="badge badge-muted"><i class="fa-solid fa-power-off"></i> Auto-Deploy Apagado</span>
                            @endif
                        </div>
                        <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;">
                            <input type="text" readonly value="{{ $selectedDeployment->webhook_url }}" class="form-input" style="font-family:monospace;font-size:12px;color:var(--text-primary);background:rgba(0,0,0,0.3);">
                        </div>
                        <div style="display:flex;gap:12px;align-items:center;">
                            <div style="flex:1;">
                                <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">Webhook Secret (X-Hub-Signature-256)</div>
                                <input type="text" readonly value="{{ $selectedDeployment->webhook_secret }}" class="form-input" style="font-family:monospace;font-size:12px;color:var(--text-primary);background:rgba(0,0,0,0.3);">
                            </div>
                            <button wire:click="generateNewSecret" class="btn btn-ghost btn-sm" style="margin-top:18px;"><i class="fa-solid fa-rotate"></i> Regenerar</button>
                        </div>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:12px;">
                            Configura esta URL en GitHub/GitLab para que LaraPanel despliegue automáticamente al hacer push a la rama <strong>{{ $selectedDeployment->branch }}</strong>.
                        </p>
                    </div>

                    {{-- Edit Form --}}
                    <form wire:submit.prevent="save">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div class="form-group">
                                <label class="form-label">Dominio / Proyecto</label>
                                <div style="position:relative;">
                                    <select wire:model.live="domain_name" class="form-input" style="appearance:none;">
                                        <option value="">Selecciona un dominio...</option>
                                        @foreach($availableDomains as $domain)
                                            <option value="{{ $domain }}">{{ $domain }}</option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down" style="position:absolute;right:12px;top:12px;color:var(--text-muted);font-size:12px;pointer-events:none;"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">URL del Repositorio</label>
                                <input type="text" wire:model="repository_url" class="form-input">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:16px;">
                            <label class="form-label">Ruta de Instalación</label>
                            <input type="text" wire:model="deploy_path" class="form-input">
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div class="form-group">
                                <label class="form-label">Rama</label>
                                <input type="text" wire:model="branch" class="form-input">
                            </div>
                            <div class="form-group" style="display:flex;align-items:center;padding-top:28px;">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                                    <input type="checkbox" wire:model="auto_deploy"> Activar Auto-Deploy
                                </label>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:24px;">
                            <label class="form-label">Script de Post-Despliegue (Bash)</label>
                            <textarea wire:model="deploy_script" class="form-input" rows="6" style="font-family:monospace;font-size:12px;line-height:1.6;"></textarea>
                        </div>

                        <div style="text-align:right;">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Actualizar</button>
                        </div>
                    </form>
                </div>
            @elseif($activeTab === 'logs')
                @if($selectedLog)
                    <div class="glass" style="padding:20px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                            <div>
                                <button wire:click="$set('selectedLog', null)" class="btn btn-ghost btn-sm" style="margin-right:12px;">
                                    <i class="fa-solid fa-arrow-left"></i> Volver
                                </button>
                                <span class="badge {{ $selectedLog->statusBadgeClass() }}">{{ strtoupper($selectedLog->status) }}</span>
                                <span style="font-size:12px;color:var(--text-muted);margin-left:12px;">
                                    {{ $selectedLog->created_at->format('Y-m-d H:i:s') }}
                                </span>
                            </div>
                            <div style="font-size:12px;font-family:monospace;color:var(--accent-light);">
                                {{ substr($selectedLog->commit_hash ?? 'N/A', 0, 7) }}
                            </div>
                        </div>
                        <pre style="background:rgba(0,0,0,0.5);border:1px solid var(--glass-border);border-radius:8px;padding:16px;font-family:monospace;font-size:11px;color:#cdd6f4;line-height:1.6;white-space:pre-wrap;max-height:500px;overflow-y:auto;">{{ $selectedLog->output }}</pre>
                    </div>
                @else
                    <div class="glass" style="padding:0;">
                        @if($selectedDeployment->logs->isEmpty())
                        <div style="text-align:center;padding:40px;color:var(--text-muted);">
                            <i class="fa-solid fa-inbox" style="font-size:32px;opacity:0.3;margin-bottom:12px;display:block;"></i>
                            No hay despliegues registrados.
                        </div>
                        @else
                        <table class="table" style="width:100%;">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th>Commit</th>
                                    <th>Gatillo</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($selectedDeployment->logs as $log)
                                <tr>
                                    <td style="font-size:12px;">{{ $log->created_at->diffForHumans() }}</td>
                                    <td><span class="badge {{ $log->statusBadgeClass() }}" style="font-size:10px;">{{ strtoupper($log->status) }}</span></td>
                                    <td>
                                        <div style="font-size:12px;font-family:monospace;color:var(--text-primary);">{{ substr($log->commit_hash ?? '—', 0, 7) }}</div>
                                        <div style="font-size:10px;color:var(--text-muted);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $log->commit_message ?? '—' }}</div>
                                    </td>
                                    <td style="font-size:11px;color:var(--text-muted);">{{ ucfirst($log->triggered_by) }}</td>
                                    <td>
                                        <button wire:click="viewLog({{ $log->id }})" class="btn btn-ghost btn-sm"><i class="fa-solid fa-eye"></i> Ver Log</button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @endif
                    </div>
                @endif
            @endif
        @else
            <div class="glass" style="padding:60px 20px;text-align:center;">
                <i class="fa-brands fa-git-alt" style="font-size:48px;opacity:0.2;margin-bottom:16px;display:block;"></i>
                <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">Git Deploy</h3>
                <p style="color:var(--text-secondary);font-size:13px;max-width:400px;margin:0 auto 24px auto;">
                    Conecta tus repositorios de GitHub o GitLab para desplegar automáticamente tus aplicaciones cuando hagas push.
                </p>
                <button wire:click="createNew" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Configurar Repositorio
                </button>
            </div>
        @endif
    </div>
</div>
