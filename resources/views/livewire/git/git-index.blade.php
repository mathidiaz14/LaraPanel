<div class="lp-split git-split" style="display:grid;grid-template-columns:260px minmax(0,1fr);gap:24px;align-items:start;">
    @if($isDeploying)
        <div wire:poll.2s="pollDeploymentStatus" style="display:none;"></div>
    @endif

    <div class="glass lp-panel git-sidebar" style="padding:16px;">
        <div class="git-sidebar-header">
            <h2 class="panel-title" style="margin:0;">Repositorios</h2>
            <button wire:click="createNew" class="btn btn-primary btn-sm btn-icon" aria-label="Nuevo repositorio" title="Nuevo repositorio">
                <i class="fa-solid fa-plus"></i>
            </button>
        </div>

        <div class="git-repo-list">
            @foreach($deployments as $dep)
                <button wire:click="selectDeployment({{ $dep->id }})" class="git-repo-item {{ ($selectedDeployment && $selectedDeployment->id === $dep->id && !$isCreating) ? 'active' : '' }}">
                    <span class="git-repo-icon"><i class="fa-brands fa-git-alt"></i></span>
                    <span class="git-repo-main">
                        <span class="git-repo-name">{{ $dep->domain_name }}</span>
                        <span class="git-repo-meta"><i class="fa-solid fa-code-branch"></i> {{ $dep->branch }} · {{ $dep->auto_deploy ? 'Auto' : 'Manual' }}</span>
                    </span>
                    @if($dep->last_deployed_at)
                        <span class="git-repo-dot" title="{{ $dep->last_deployed_at->format('d/m/Y H:i') }}"></span>
                    @endif
                </button>
            @endforeach

            @if($deployments->isEmpty())
                <div class="empty-state" style="padding:28px 8px;">
                    <i class="fa-brands fa-git-alt empty-state-icon" style="font-size:28px;"></i>
                    <span class="empty-state-text" style="margin:0;">Todavía no hay repositorios configurados.</span>
                </div>
            @endif
        </div>

        <a href="{{ route('deploy.pipelines.index') }}" class="btn btn-ghost btn-sm git-pipelines-link">
            <i class="fa-solid fa-diagram-project"></i> Ver Pipelines
            <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:10px;"></i>
        </a>
    </div>

    <div>
        @if(session()->has('message'))
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> {{ session('message') }}</div>
        @endif

        <div class="stats-row git-stats">
            <div class="glass stat-card accent">
                <div class="stat-label">Repositorios</div>
                <div class="stat-value">{{ $stats['repositories'] ?? 0 }}</div>
                <div class="stat-sub">Configurados en tu cuenta</div>
                <i class="fa-brands fa-git-alt stat-icon"></i>
            </div>
            <div class="glass stat-card success">
                <div class="stat-label">Auto-Deploy</div>
                <div class="stat-value">{{ $stats['auto_deploy'] ?? 0 }}</div>
                <div class="stat-sub">Con webhook activo</div>
                <i class="fa-solid fa-bolt stat-icon"></i>
            </div>
            <div class="glass stat-card warning">
                <div class="stat-label">Éxito (30 días)</div>
                <div class="stat-value">{{ $stats['success_rate'] ?? 0 }}%</div>
                <div class="stat-sub">Despliegues correctos</div>
                <i class="fa-solid fa-chart-line stat-icon"></i>
            </div>
            <div class="glass stat-card">
                <div class="stat-label">Último deploy</div>
                <div class="stat-value" style="font-size:18px;">{{ $stats['last_deploy'] ?? 'Sin despliegues' }}</div>
                <div class="stat-sub">Historial reciente</div>
                <i class="fa-regular fa-clock stat-icon"></i>
            </div>
        </div>

        @if($isCreating)
            <div class="glass lp-panel git-panel" style="padding:24px;">
                <div class="git-form-header">
                    <h2 class="panel-title" style="font-size:18px;margin:0;">Configurar Nuevo Repositorio</h2>
                    <button type="button" wire:click="cancelCreate" class="btn btn-ghost btn-sm"><i class="fa-solid fa-xmark"></i> Cancelar</button>
                </div>

                <form wire:submit.prevent="save">
                    <div class="git-form-grid">
                        <div class="form-group">
                            <label class="form-label">Dominio / Proyecto</label>
                            <select wire:model.live="domain_name" class="form-input">
                                <option value="">Selecciona un dominio...</option>
                                @foreach($availableDomains as $domain)
                                    <option value="{{ $domain }}">{{ $domain }}</option>
                                @endforeach
                            </select>
                            @error('domain_name') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">URL del Repositorio (HTTPS o SSH)</label>
                            <input type="text" wire:model="repository_url" class="form-input" placeholder="https://github.com/usuario/repo.git">
                            <div class="git-help">Para repositorios privados usá <code>https://usuario:TOKEN@gitlab.com/usuario/repo.git</code>, <code>git@github.com:usuario/repo.git</code> o <code>ssh://git@host/repo.git</code>.</div>
                            @error('repository_url') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group git-form-full">
                            <label class="form-label">Ruta de Instalación (Directorio del Repo)</label>
                            <input type="text" wire:model="deploy_path" class="form-input" placeholder="/var/www/ejemplo.com/public_html">
                            <div class="git-help"><i class="fa-solid fa-info-circle"></i> Si el repositorio ya existe en el servidor, LaraPanel solo lo actualizará sin clonarlo de nuevo.</div>
                            @error('deploy_path') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">Rama a trackear</label>
                            <input type="text" wire:model="branch" class="form-input" placeholder="main">
                            @error('branch') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group git-check-field">
                            <label><input type="checkbox" wire:model="auto_deploy"> Activar Auto-Deploy (vía Webhook)</label>
                        </div>
                        <div class="form-group git-form-full">
                            <label class="form-label">Script de Post-Despliegue (Bash)</label>
                            <textarea wire:model="deploy_script" class="form-input git-script" rows="6"></textarea>
                            <div class="git-help">Se ejecuta en la raíz del proyecto después de actualizar el código.</div>
                        </div>
                    </div>

                    <div class="git-form-actions">
                        <button type="button" wire:click="cancelCreate" class="btn btn-ghost">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar Configuración</button>
                    </div>
                </form>
            </div>
        @elseif($selectedDeployment)
            <div class="lp-tabs git-tabs">
                <button wire:click="$set('activeTab', 'config')" class="lp-tab {{ $activeTab === 'config' ? 'active' : '' }}">
                    <i class="fa-solid fa-gear"></i> Configuración
                </button>
                <button wire:click="$set('activeTab', 'logs')" class="lp-tab {{ $activeTab === 'logs' ? 'active' : '' }}">
                    <i class="fa-solid fa-list-check"></i> Historial de Despliegues
                </button>
                <div class="lp-tabs-actions">
                    <a href="{{ route('deploy.pipelines.index') }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-diagram-project"></i> Pipelines</a>
                </div>
            </div>

            @if($activeTab === 'config')
                <div class="glass lp-panel git-panel" style="padding:24px;">
                    <div class="git-detail-header">
                        <div>
                            <h2 class="panel-title" style="font-size:18px;margin:0;overflow-wrap:anywhere;">
                                <span class="git-detail-icon"><i class="fa-brands fa-git-alt"></i></span>
                                {{ $selectedDeployment->domain_name }}
                            </h2>
                            <div class="git-detail-meta">
                                <span class="badge badge-accent"><i class="fa-solid fa-code-branch"></i> {{ $selectedDeployment->branch }}</span>
                                <span title="{{ $selectedDeployment->last_deployed_at?->format('d/m/Y H:i') ?? '' }}">
                                    <i class="fa-regular fa-clock"></i>
                                    {{ $selectedDeployment->last_deployed_at ? 'Último deploy ' . $selectedDeployment->last_deployed_at->diffForHumans() : 'Sin despliegues aún' }}
                                </span>
                            </div>
                        </div>
                        <div class="git-detail-actions">
                            <button wire:click="deployNow" class="btn btn-primary btn-sm" wire:loading.attr="disabled" title="Despliega la rama configurada en segundo plano">
                                <span wire:loading.remove><i class="fa-solid fa-arrows-rotate"></i> Forzar Actualización</span>
                                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Iniciando...</span>
                            </button>
                            <button wire:click="delete" wire:confirm="¿Eliminar esta configuración? No se borrarán los archivos del servidor." class="btn btn-danger btn-sm btn-icon" aria-label="Eliminar repositorio" title="Eliminar repositorio">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    @if($isDeploying)
                        <div class="lp-loading-toast" style="position:static;margin:0 0 18px auto;">
                            <i class="fa-solid fa-spinner fa-spin"></i> Despliegue en segundo plano. Actualizando historial...
                        </div>
                    @endif

                    <div class="git-info-card">
                        <div class="git-info-header">
                            <h3><i class="fa-brands fa-git-alt"></i> Estado del Repositorio Local</h3>
                            <button wire:click="refreshRepoStatus" class="btn btn-ghost btn-sm" wire:loading.attr="disabled" wire:target="refreshRepoStatus">
                                <span wire:loading.remove wire:target="refreshRepoStatus"><i class="fa-solid fa-rotate"></i> Actualizar</span>
                                <span wire:loading wire:target="refreshRepoStatus"><i class="fa-solid fa-spinner fa-spin"></i></span>
                            </button>
                        </div>

                        @if(empty($repoStatus))
                            <div class="empty-state" style="padding:24px 12px;"><span class="empty-state-text" style="margin:0;">Todavía no se consultó el estado local.</span></div>
                        @elseif($repoStatus['status'] === 'not_found')
                            <span class="badge badge-warning">Directorio Inexistente</span>
                            <p class="git-help">{{ $repoStatus['message'] }}</p>
                        @elseif($repoStatus['status'] === 'not_initialized')
                            <span class="badge badge-warning">No Inicializado</span>
                            <p class="git-help">{{ $repoStatus['message'] }}</p>
                        @else
                            <div class="git-status-grid">
                                <div>
                                    <span class="git-status-label">Rama Actual</span>
                                    <strong><i class="fa-solid fa-code-branch"></i> {{ $repoStatus['branch'] }}</strong>
                                </div>
                                <div>
                                    <span class="git-status-label">Último Commit Descargado</span>
                                    <strong class="git-mono">{{ $repoStatus['commit'] }}</strong>
                                </div>
                            </div>
                            @if($repoStatus['has_changes'])
                                <div class="git-changes-warning"><i class="fa-solid fa-triangle-exclamation"></i> Hay archivos modificados localmente:</div>
                                <pre class="git-console">{{ $repoStatus['changes'] }}</pre>
                            @else
                                <div class="git-clean"><i class="fa-solid fa-circle-check"></i> Directorio de trabajo limpio</div>
                            @endif
                        @endif
                    </div>

                    <div class="git-webhook-card">
                        <div class="git-info-header">
                            <h3><i class="fa-solid fa-link"></i> Webhook URL</h3>
                            @if($selectedDeployment->auto_deploy)
                                <span class="badge badge-success"><i class="fa-solid fa-bolt"></i> Auto-Deploy Activo</span>
                            @else
                                <span class="badge badge-muted"><i class="fa-solid fa-power-off"></i> Auto-Deploy Apagado</span>
                            @endif
                        </div>

                        <div class="git-copy-row">
                            <input id="git-webhook-url-{{ $selectedDeployment->id }}" type="text" readonly value="{{ $selectedDeployment->webhook_url }}" class="form-input git-mono">
                            <button type="button" class="btn btn-ghost btn-sm" data-copy-target="git-webhook-url-{{ $selectedDeployment->id }}" title="Copiar URL" aria-label="Copiar URL"><i class="fa-regular fa-copy"></i> Copiar</button>
                            <button wire:click="testWebhook" class="btn btn-ghost btn-sm" wire:loading.attr="disabled" wire:target="testWebhook">
                                <span wire:loading.remove wire:target="testWebhook"><i class="fa-solid fa-satellite-dish"></i> Probar</span>
                                <span wire:loading wire:target="testWebhook"><i class="fa-solid fa-spinner fa-spin"></i> Probando...</span>
                            </button>
                        </div>

                        @if($webhookTestResult)
                            <div class="alert {{ $webhookTestResult['ok'] ? 'alert-success' : 'alert-danger' }}" style="margin:12px 0 0;">
                                <i class="fa-solid {{ $webhookTestResult['ok'] ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i>
                                {{ $webhookTestResult['message'] }}
                            </div>
                        @endif

                        <div class="git-copy-row" style="margin-top:12px;">
                            <div style="flex:1;min-width:0;">
                                <span class="git-status-label">Webhook Secret (X-Hub-Signature-256)</span>
                                <input id="git-webhook-secret-{{ $selectedDeployment->id }}" type="password" readonly value="{{ $selectedDeployment->webhook_secret }}" class="form-input git-mono">
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" data-toggle-target="git-webhook-secret-{{ $selectedDeployment->id }}" title="Mostrar u ocultar secreto" aria-label="Mostrar u ocultar secreto"><i class="fa-regular fa-eye"></i></button>
                            <button type="button" class="btn btn-ghost btn-sm" data-copy-target="git-webhook-secret-{{ $selectedDeployment->id }}" title="Copiar secreto" aria-label="Copiar secreto"><i class="fa-regular fa-copy"></i> Copiar</button>
                            <button wire:click="generateNewSecret" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate"></i> Regenerar</button>
                        </div>

                        <p class="git-help" style="margin-top:12px;">
                            Configurá esta URL y secreto en GitHub/GitLab. El despliegue se ejecuta en segundo plano y la respuesta del webhook es inmediata.
                        </p>
                    </div>

                    <form wire:submit.prevent="save" class="git-edit-form">
                        <div class="git-form-grid">
                            <div class="form-group">
                                <label class="form-label">Dominio / Proyecto</label>
                                <select wire:model.live="domain_name" class="form-input">
                                    @foreach($availableDomains as $domain)
                                        <option value="{{ $domain }}">{{ $domain }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">URL del Repositorio</label>
                                <input type="text" wire:model="repository_url" class="form-input">
                                @error('repository_url') <div class="form-error">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group git-form-full">
                                <label class="form-label">Ruta de Instalación</label>
                                <input type="text" wire:model="deploy_path" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Rama</label>
                                <input type="text" wire:model="branch" class="form-input">
                            </div>
                            <div class="form-group git-check-field">
                                <label><input type="checkbox" wire:model="auto_deploy"> Activar Auto-Deploy</label>
                            </div>
                            <div class="form-group git-form-full">
                                <label class="form-label">Script de Post-Despliegue (Bash)</label>
                                <textarea wire:model="deploy_script" class="form-input git-script" rows="6"></textarea>
                            </div>
                        </div>
                        <div class="git-form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Actualizar</button>
                        </div>
                    </form>
                </div>
            @else
                <div class="glass lp-panel" style="padding:0;">
                    <div class="filters-bar" style="padding:16px;margin:0;">
                        <div class="lp-search">
                            <i class="fa-solid fa-magnifying-glass lp-search-icon"></i>
                            <input type="text" wire:model.live="logSearch" class="form-input" placeholder="Buscar commit, mensaje o gatillo...">
                        </div>
                        <select wire:model.live="logStatus" class="form-input git-filter-select">
                            <option value="all">Todos los estados</option>
                            <option value="success">Exitosos</option>
                            <option value="failed">Fallidos</option>
                            <option value="running">En curso</option>
                        </select>
                        <select wire:model.live="logTrigger" class="form-input git-filter-select">
                            <option value="all">Todos los gatillos</option>
                            <option value="manual">Manual</option>
                            <option value="webhook">Webhook</option>
                        </select>
                        <select wire:model.live="perPage" class="form-input git-filter-select">
                            <option value="10">10 por página</option>
                            <option value="25">25 por página</option>
                            <option value="50">50 por página</option>
                        </select>
                        <button wire:click="resetLogFilters" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Limpiar</button>
                    </div>

                    @if($logs->isEmpty())
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox empty-state-icon"></i>
                            <span class="empty-state-text">No hay despliegues que coincidan con los filtros.</span>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="lp-table git-log-table" style="margin:0;">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                        <th>Commit</th>
                                        <th>Gatillo</th>
                                        <th>Duración</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($logs as $log)
                                        <tr>
                                            <td>
                                                <div style="font-size:12px;">{{ $log->created_at->diffForHumans() }}</div>
                                                <div class="git-help">{{ $log->created_at->format('d/m/Y H:i') }}</div>
                                            </td>
                                            <td><span class="badge {{ $log->statusBadgeClass() }}">{{ strtoupper($log->status) }}</span></td>
                                            <td>
                                                <div class="git-mono" style="font-size:12px;">{{ substr($log->commit_hash ?? '—', 0, 7) }}</div>
                                                <div class="git-help" style="max-width:180px;">{{ $log->commit_message ?? ($log->requestedCommit() === '—' ? '—' : 'Solicitado ' . substr($log->requestedCommit(), 0, 7)) }}</div>
                                            </td>
                                            <td style="font-size:11px;color:var(--text-muted);">{{ ucfirst($log->triggered_by) }}</td>
                                            <td style="font-size:11px;color:var(--text-muted);">{{ $log->durationLabel() }}</td>
                                            <td>
                                                <button wire:click="viewLog({{ $log->id }})" class="btn btn-ghost btn-sm"><i class="fa-solid fa-eye"></i> Ver Log</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($logs->lastPage() > 1)
                            <div class="git-pagination">
                                <button wire:click="setPage({{ $logs->currentPage() - 1 }})" class="btn btn-ghost btn-sm" @if($logs->onFirstPage()) disabled @endif>
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                                @foreach(range(1, $logs->lastPage()) as $page)
                                    <button wire:click="setPage({{ $page }})" class="btn btn-sm {{ $page === $logs->currentPage() ? 'btn-primary' : 'btn-ghost' }}">{{ $page }}</button>
                                @endforeach
                                <button wire:click="setPage({{ $logs->currentPage() + 1 }})" class="btn btn-ghost btn-sm" @if(!$logs->hasMorePages()) disabled @endif>
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                                <span>{{ $logs->total() }} despliegues</span>
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        @else
            <div class="glass lp-panel empty-state" style="padding:clamp(24px,8vw,60px) 16px;">
                <i class="fa-brands fa-git-alt empty-state-icon" style="font-size:48px;"></i>
                <h3 class="panel-title" style="font-size:18px;margin-bottom:8px;">Git Deploy</h3>
                <p class="empty-state-text">Conectá tus repositorios de GitHub o GitLab para desplegar automáticamente tus aplicaciones cuando hagas push.</p>
                <button wire:click="createNew" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Configurar Repositorio</button>
            </div>
        @endif
    </div>

    @if($showLogModal && $selectedLog)
        <div class="lp-modal-backdrop" wire:click.self="closeLogModal">
            <div class="lp-modal glass-elevated git-log-modal" role="dialog" aria-modal="true" aria-label="Detalle del despliegue">
                <div class="lp-modal-header">
                    <div>
                        <h3 class="panel-title" style="margin:0;">Detalle del despliegue</h3>
                        <div class="git-help">{{ $selectedLog->created_at->format('d/m/Y H:i:s') }}</div>
                    </div>
                    <button type="button" wire:click="closeLogModal" class="lp-modal-close" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="lp-modal-body">
                    <div class="git-log-summary">
                        <span class="badge {{ $selectedLog->statusBadgeClass() }}">{{ strtoupper($selectedLog->status) }}</span>
                        <span class="badge badge-muted">{{ ucfirst($selectedLog->triggered_by) }}</span>
                        <span class="badge badge-accent"><i class="fa-solid fa-clock"></i> {{ $selectedLog->durationLabel() }}</span>
                    </div>
                    <div class="git-log-commits">
                        <div><span>Solicitado</span><strong class="git-mono">{{ $selectedLog->requestedCommit() }}</strong></div>
                        <div><span>Desplegado</span><strong class="git-mono">{{ $selectedLog->commit_hash ?: '—' }}</strong></div>
                    </div>
                    <pre class="git-console git-log-console">{{ $selectedLog->output ?: 'Sin salida registrada.' }}</pre>
                </div>
                <div class="lp-modal-footer">
                    @if($selectedLog->commit_hash)
                        <button type="button" wire:click="redeployCommit({{ $selectedLog->id }})" wire:confirm="¿Volver a desplegar este commit?" class="btn btn-ghost"><i class="fa-solid fa-rotate-left"></i> Redesplegar commit</button>
                    @endif
                    <button type="button" wire:click="closeLogModal" class="btn btn-primary">Cerrar</button>
                </div>
            </div>
        </div>
    @endif
</div>
