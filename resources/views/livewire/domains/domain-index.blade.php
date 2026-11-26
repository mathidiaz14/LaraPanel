<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">Gestión de Dominios</h1>
            <p class="page-subtitle">
                {{ $stats['total'] }} dominio(s), {{ $stats['subdomains'] }} subdominio(s) y {{ $stats['proxies'] }} proxy/proxies
            </p>
        </div>
        <a href="{{ route('domains.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Nuevo Dominio
        </a>
    </div>

    <div class="stats-grid domain-stats">
        <div class="stat-card accent">
            <div class="stat-label">Dominios</div>
            <div class="stat-value">{{ $stats['total'] }}</div>
            <div class="stat-sub">{{ $stats['subdomains'] }} subdominios</div>
            <i class="fa-solid fa-globe stat-icon"></i>
        </div>
        <div class="stat-card success">
            <div class="stat-label">Activos</div>
            <div class="stat-value">{{ $stats['active'] }}</div>
            <div class="stat-sub">{{ $stats['suspended'] }} suspendidos</div>
            <i class="fa-solid fa-circle-check stat-icon"></i>
        </div>
        <div class="stat-card {{ $stats['expiring'] > 0 ? 'warning' : 'success' }}">
            <div class="stat-label">SSL activo</div>
            <div class="stat-value">{{ $stats['ssl'] }}</div>
            <div class="stat-sub">{{ $stats['expiring'] }} expirando pronto</div>
            <i class="fa-solid fa-lock stat-icon"></i>
        </div>
        <div class="stat-card {{ $stats['proxies'] > 0 ? 'accent' : '' }}">
            <div class="stat-label">Proxy / Staging</div>
            <div class="stat-value">{{ $stats['proxies'] }}</div>
            <div class="stat-sub">configuraciones avanzadas</div>
            <i class="fa-solid fa-network-wired stat-icon"></i>
        </div>
    </div>

    @if($successMessage)
    <div class="alert alert-success" x-data x-init="setTimeout(() => $el.remove(), 4000)">
        <i class="fa-solid fa-circle-check"></i> {{ $successMessage }}
    </div>
    @endif

    @if($errorMessage)
    <div class="alert alert-danger" x-data x-init="setTimeout(() => $el.remove(), 6000)">
        <i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}
    </div>
    @endif

    <div class="filters-bar">
        <div class="lp-search" style="flex:1;max-width:320px;margin-bottom:0;">
            <i class="fa-solid fa-magnifying-glass lp-search-icon"></i>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar dominio o ruta..." class="form-input" style="margin-bottom:0;">
        </div>

        <div style="display:flex;gap:6px;">
            @foreach(['all' => 'Todos', 'active' => 'Activos', 'suspended' => 'Suspendidos', 'error' => 'Errores'] as $val => $label)
            <button wire:click="$set('filter','{{ $val }}')" class="btn {{ $filter === $val ? 'btn-primary' : 'btn-ghost' }} btn-sm">
                {{ $label }}
            </button>
            @endforeach
        </div>

        <select wire:model.live="typeFilter" class="form-input domain-filter-select" style="margin-bottom:0;width:auto;">
            <option value="all">Todos los tipos</option>
            <option value="main">Dominios</option>
            <option value="subdomain">Subdominios</option>
            <option value="proxy">Proxy</option>
        </select>

        <select wire:model.live="sslFilter" class="form-input domain-filter-select" style="margin-bottom:0;width:auto;">
            <option value="all">SSL: todos</option>
            <option value="with">Con SSL</option>
            <option value="without">Sin SSL</option>
            <option value="expiring">Expirando</option>
        </select>

        <select wire:model.live="perPage" class="form-input domain-filter-select" style="margin-bottom:0;width:auto;">
            <option value="10">10 por página</option>
            <option value="25">25 por página</option>
            <option value="50">50 por página</option>
        </select>

        <button wire:click="resetFilters" class="btn btn-ghost btn-sm" title="Limpiar filtros">
            <i class="fa-solid fa-eraser"></i>
        </button>
    </div>

    @if(!empty($selected))
    <div class="domain-bulk-bar">
        <span class="domain-bulk-count">{{ count($selected) }}</span>
        <span class="domain-bulk-label">seleccionado(s)</span>
        <div class="domain-bulk-divider"></div>
        <button wire:click="bulkSuspend" wire:confirm="¿Suspender los dominios seleccionados?" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-pause"></i> Suspender
        </button>
        <button wire:click="bulkUnsuspend" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-play"></i> Reactivar
        </button>
        <button wire:click="bulkDeploy" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-rotate"></i> Desplegar
        </button>
        <button wire:click="bulkRenewSsl" wire:confirm="¿Renovar el SSL de los dominios seleccionados?" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-certificate"></i> Renovar SSL
        </button>
        <button wire:click="exportCsv" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-file-csv"></i> Exportar
        </button>
        <button wire:click="clearSelection" class="btn btn-ghost btn-sm" style="margin-left:auto;">
            <i class="fa-solid fa-xmark"></i> Limpiar
        </button>
    </div>
    @endif

    <div class="glass domain-panel">
        @if($groups->isEmpty())
            <div style="padding:clamp(24px,8vw,60px);text-align:center;">
                <div style="font-size:40px;opacity:0.3;margin-bottom:16px;">
                    <i class="fa-solid fa-globe"></i>
                </div>
                <p style="color:var(--text-secondary);margin-bottom:18px;">No hay dominios que coincidan con los filtros.</p>
                <a href="{{ route('domains.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Crear dominio
                </a>
            </div>
        @else
        <div class="table-responsive">
            <table class="lp-table domain-table">
                <thead>
                    <tr>
                        <th style="width:36px;">
                            <input type="checkbox" wire:click="toggleVisibleSelection" class="domain-checkbox"
                                   @if(!empty($visibleDomainIds) && count(array_intersect(array_map('intval', $selected), array_map('intval', $visibleDomainIds))) === count($visibleDomainIds)) checked @endif
                                   title="Seleccionar dominios visibles">
                        </th>
                        <th>Dominio</th>
                        <th>Tipo / PHP</th>
                        <th>SSL</th>
                        <th>Salud</th>
                        <th>Creado</th>
                        <th style="text-align:right">Acciones</th>
                    </tr>
                </thead>

                @foreach($groups as $groupName => $groupData)
                    @php
                        $domain = $groupData['main'] ?? $groupData['subdomains']->first();
                        $subdomains = $groupData['subdomains'];
                        $hasSubs = $subdomains->isNotEmpty();
                    @endphp
                    <tbody x-data="{ expanded: false }">
                        <tr wire:key="domain-{{ $domain?->id ?? $groupName }}">
                            <td>
                                @if($groupData['mainVisible'] && $domain)
                                    <input type="checkbox" value="{{ $domain->id }}" wire:model.live="selected" class="domain-checkbox">
                                @endif
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;min-width:220px;">
                                    @if($hasSubs)
                                    <button @click="expanded = !expanded" class="btn btn-ghost btn-sm domain-expand" title="Mostrar subdominios">
                                        <i class="fa-solid fa-chevron-right" :style="expanded ? 'transform:rotate(90deg)' : ''"></i>
                                    </button>
                                    @endif
                                    <div class="domain-icon"><i class="fa-solid fa-globe"></i></div>
                                    <div style="min-width:0;">
                                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                            <span style="font-weight:600;font-size:14px;color:var(--text-primary);">{{ $groupName }}</span>
                                            @if($hasSubs)
                                            <span class="domain-pill"><i class="fa-solid fa-code-branch"></i> {{ $subdomains->count() }}</span>
                                            @endif
                                            @if($domain?->getProxyPort())
                                            <span class="domain-pill">:{{ $domain->getProxyPort() }}</span>
                                            @endif
                                            @if($domain?->onStaging())
                                            <span class="domain-pill">staging</span>
                                            @endif
                                        </div>
                                        <div style="display:flex;align-items:center;gap:4px;font-size:11px;color:var(--text-muted);">
                                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:280px;" title="{{ $domain?->document_root }}">{{ $domain?->document_root }}</span>
                                            @if($domain)
                                            <button type="button" class="domain-copy" title="Copiar ruta" onclick="navigator.clipboard.writeText('{{ $domain->document_root }}'); this.title='Copiado'; setTimeout(() => this.title='Copiar ruta', 1200)">
                                                <i class="fa-regular fa-copy"></i>
                                            </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-muted">{{ ucfirst($domain?->type ?? '') }}</span>
                                <div style="font-size:11px;color:var(--text-secondary);font-family:monospace;margin-top:4px;">PHP {{ $domain?->php_version }}</div>
                            </td>
                            <td>
                                @if($domain?->ssl_enabled)
                                <span class="badge badge-success">
                                    <i class="fa-solid fa-lock" style="font-size:9px;"></i> SSL
                                    @if($domain->sslIsExpiringSoon()) · {{ $domain->sslExpiresInDays() }}d @endif
                                </span>
                                @else
                                <span class="badge badge-muted">Sin SSL</span>
                                @endif
                            </td>
                            <td>
                                @php $healthStatus = $domain ? ($health[$domain->id] ?? ['level' => 'muted', 'label' => '—']) : ['level' => 'muted', 'label' => '—']; @endphp
                                <span class="badge domain-health-{{ $healthStatus['level'] }}">{{ $healthStatus['label'] }}</span>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted);">
                                {{ $domain?->created_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td style="text-align:right;">
                                @if($groupData['mainVisible'] && $domain)
                                <div x-data="{ open: false, left: 0, top: 0 }" class="domain-actions-wrap" @click.outside="open=false">
                                    <button type="button" class="btn btn-ghost btn-sm" title="Acciones"
                                            @click="const r=$el.getBoundingClientRect(); left=Math.min(r.left, window.innerWidth-220); top=Math.min(r.bottom+4, window.innerHeight-300); open=!open">
                                        <i class="fa-solid fa-ellipsis"></i>
                                    </button>
                                    <div class="domain-menu" x-show="open" x-cloak
                                         x-bind:style="'position:fixed;left:'+left+'px;top:'+top+'px;'">
                                        <button wire:click="checkDomain({{ $domain->id }})" @click="open=false"><i class="fa-solid fa-heart-pulse"></i> Comprobar</button>
                                        <button wire:click="showConfig({{ $domain->id }})" @click="open=false"><i class="fa-solid fa-file-code"></i> Ver configuración</button>
                                        <button wire:click="deployDomain({{ $domain->id }})" @click="open=false"><i class="fa-solid fa-rotate"></i> Redesplegar</button>
                                        <button wire:click="renewDomainSsl({{ $domain->id }})" @click="open=false"><i class="fa-solid fa-certificate"></i> Renovar SSL</button>
                                        <a href="{{ route('ssl.issue', ['domain' => $domain->id]) }}" @click="open=false"><i class="fa-solid fa-lock"></i> Gestionar SSL</a>
                                        <a href="{{ route('domains.error-pages', $domain->id) }}" @click="open=false"><i class="fa-solid fa-file-circle-exclamation"></i> Páginas de error</a>
                                        <a href="{{ route('domains.staging', $domain->id) }}" @click="open=false"><i class="fa-solid fa-network-wired"></i> Staging / Balanceo</a>
                                        <div class="domain-menu-divider"></div>
                                        @if($domain->status === 'active')
                                        <button wire:click="suspendDomain({{ $domain->id }})" wire:confirm="¿Suspender {{ $groupName }}?" @click="open=false"><i class="fa-solid fa-pause"></i> Suspender</button>
                                        @elseif($domain->status === 'suspended')
                                        <button wire:click="unsuspendDomain({{ $domain->id }})" @click="open=false"><i class="fa-solid fa-play"></i> Reactivar</button>
                                        @endif
                                        <button wire:click="editDomain({{ $domain->id }})" @click="open=false"><i class="fa-solid fa-pen"></i> Editar</button>
                                        <button wire:click="confirmDelete({{ $domain->id }})" @click="open=false" class="domain-menu-danger"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                    </div>
                                </div>
                                @endif
                            </td>
                        </tr>

                        @foreach($subdomains as $sub)
                        <tr wire:key="domain-{{ $sub->id }}" x-show="expanded" style="background:var(--glass-bg);display:none;" x-transition>
                            <td>
                                <input type="checkbox" value="{{ $sub->id }}" wire:model.live="selected" class="domain-checkbox">
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;padding-left:26px;min-width:220px;">
                                    <div class="domain-icon domain-icon-sub"><i class="fa-solid fa-code-branch"></i></div>
                                    <div style="min-width:0;">
                                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                            <span style="font-weight:600;font-size:13px;color:var(--text-secondary);">{{ $sub->name }}</span>
                                            @if($sub->getProxyPort()) <span class="domain-pill">:{{ $sub->getProxyPort() }}</span> @endif
                                            @if($sub->onStaging()) <span class="domain-pill">staging</span> @endif
                                        </div>
                                        <div style="font-size:11px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:280px;" title="{{ $sub->document_root }}">{{ $sub->document_root }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-muted">{{ ucfirst($sub->type) }}</span>
                                <div style="font-size:11px;color:var(--text-secondary);font-family:monospace;margin-top:4px;">PHP {{ $sub->php_version }}</div>
                            </td>
                            <td>
                                @if($sub->ssl_enabled)
                                <span class="badge badge-success"><i class="fa-solid fa-lock" style="font-size:9px;"></i> SSL @if($sub->sslIsExpiringSoon()) · {{ $sub->sslExpiresInDays() }}d @endif</span>
                                @else
                                <span class="badge badge-muted">Sin SSL</span>
                                @endif
                            </td>
                            <td>
                                @php $subHealth = $health[$sub->id] ?? ['level' => 'muted', 'label' => '—']; @endphp
                                <span class="badge domain-health-{{ $subHealth['level'] }}">{{ $subHealth['label'] }}</span>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted);">{{ $sub->created_at?->diffForHumans() }}</td>
                            <td style="text-align:right;">
                                <div x-data="{ open: false, left: 0, top: 0 }" class="domain-actions-wrap" @click.outside="open=false">
                                    <button type="button" class="btn btn-ghost btn-sm" title="Acciones"
                                            @click="const r=$el.getBoundingClientRect(); left=Math.min(r.left, window.innerWidth-220); top=Math.min(r.bottom+4, window.innerHeight-300); open=!open">
                                        <i class="fa-solid fa-ellipsis"></i>
                                    </button>
                                    <div class="domain-menu" x-show="open" x-cloak
                                         x-bind:style="'position:fixed;left:'+left+'px;top:'+top+'px;'">
                                        <button wire:click="checkDomain({{ $sub->id }})" @click="open=false"><i class="fa-solid fa-heart-pulse"></i> Comprobar</button>
                                        <button wire:click="showConfig({{ $sub->id }})" @click="open=false"><i class="fa-solid fa-file-code"></i> Ver configuración</button>
                                        <button wire:click="deployDomain({{ $sub->id }})" @click="open=false"><i class="fa-solid fa-rotate"></i> Redesplegar</button>
                                        <button wire:click="renewDomainSsl({{ $sub->id }})" @click="open=false"><i class="fa-solid fa-certificate"></i> Renovar SSL</button>
                                        <a href="{{ route('ssl.issue', ['domain' => $sub->id]) }}" @click="open=false"><i class="fa-solid fa-lock"></i> Gestionar SSL</a>
                                        <div class="domain-menu-divider"></div>
                                        @if($sub->status === 'active')
                                        <button wire:click="suspendDomain({{ $sub->id }})" wire:confirm="¿Suspender {{ $sub->name }}?" @click="open=false"><i class="fa-solid fa-pause"></i> Suspender</button>
                                        @elseif($sub->status === 'suspended')
                                        <button wire:click="unsuspendDomain({{ $sub->id }})" @click="open=false"><i class="fa-solid fa-play"></i> Reactivar</button>
                                        @endif
                                        <button wire:click="editDomain({{ $sub->id }})" @click="open=false"><i class="fa-solid fa-pen"></i> Editar</button>
                                        <button wire:click="confirmDelete({{ $sub->id }})" @click="open=false" class="domain-menu-danger"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>
        @endif
    </div>

    @if($groups->lastPage() > 1)
    <div class="domain-pagination">
        <button wire:click="setPage({{ $groups->currentPage() - 1 }})" class="btn btn-ghost btn-sm" @if($groups->onFirstPage()) disabled @endif>
            <i class="fa-solid fa-chevron-left"></i>
        </button>
        @foreach(range(1, $groups->lastPage()) as $page)
        <button wire:click="setPage({{ $page }})" class="btn btn-sm {{ $page === $groups->currentPage() ? 'btn-primary' : 'btn-ghost' }}">{{ $page }}</button>
        @endforeach
        <button wire:click="setPage({{ $groups->currentPage() + 1 }})" class="btn btn-ghost btn-sm" @if(!$groups->hasMorePages()) disabled @endif>
            <i class="fa-solid fa-chevron-right"></i>
        </button>
        <span style="font-size:11px;color:var(--text-muted);margin-left:8px;">{{ $groups->total() }} grupos</span>
    </div>
    @endif

    @if($deletingId)
    @php $delDomain = $allDomains->firstWhere('id', $deletingId); @endphp
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated" style="max-width:480px;">
            <div class="lp-modal-body">
                <div style="text-align:center;margin-bottom:24px;">
                    <div style="width:56px;height:56px;border-radius:50%;background:color-mix(in srgb, var(--danger) 15%, transparent);border:2px solid color-mix(in srgb, var(--danger) 30%, transparent);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                        <i class="fa-solid fa-trash" style="color:var(--danger);font-size:22px;"></i>
                    </div>
                    <h2 style="font-size:18px;font-weight:700;margin-bottom:8px;">Eliminar dominio</h2>
                    <p style="color:var(--text-secondary);font-size:14px;">
                        ¿Estás seguro de que quieres eliminar
                        <strong style="color:var(--text-primary)">{{ $delDomain?->name }}</strong>?
                    </p>
                </div>

                <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:color-mix(in srgb, var(--danger) 6%, transparent);border:1px solid color-mix(in srgb, var(--danger) 20%, transparent);border-radius:var(--radius-sm);cursor:pointer;margin-bottom:20px;">
                    <input type="checkbox" wire:model="deleteFiles" style="accent-color:var(--danger);">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--danger);">Eliminar archivos del servidor</div>
                        <div style="font-size:11px;color:var(--text-muted);">Borrará permanentemente <code>{{ $delDomain?->document_root }}</code></div>
                    </div>
                </label>
            </div>

            <div class="lp-modal-footer">
                <button wire:click="cancelDelete" class="btn btn-ghost" style="flex:1;justify-content:center;">Cancelar</button>
                <button wire:click="deleteDomain" class="btn btn-danger" style="flex:1;justify-content:center;" wire:loading.attr="disabled">
                    <i class="fa-solid fa-trash"></i> Eliminar dominio
                </button>
            </div>
        </div>
    </div>
    @endif

    @if($editingId)
    @php $editDom = $allDomains->firstWhere('id', $editingId); @endphp
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated" style="max-width:500px;">
            <div class="lp-modal-header">
                <h2>Editar {{ $editDom?->name }}</h2>
                <button wire:click="cancelEdit" class="btn btn-ghost btn-sm" style="padding:0;width:32px;height:32px;justify-content:center;">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>

            <div class="lp-modal-body">
                <div class="form-group">
                    <label class="form-label">Ruta / Document Root</label>
                    <input wire:model="editPath" type="text" class="form-input">
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Ruta absoluta hacia la carpeta public de la aplicación.</div>
                    @error('editPath') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">Versión de PHP</label>
                    <select wire:model="editPhp" class="form-input">
                        @foreach($phpVersions as $ver)
                        <option value="{{ $ver }}">PHP {{ $ver }}</option>
                        @endforeach
                    </select>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">El servidor web (Nginx) se recargará automáticamente al aplicar.</div>
                    @error('editPhp') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="lp-modal-footer">
                <button wire:click="cancelEdit" class="btn btn-ghost">Cancelar</button>
                <button wire:click="updateDomain" class="btn btn-primary" wire:loading.attr="disabled">Guardar Cambios</button>
            </div>
        </div>
    </div>
    @endif

    @if($configDomainId)
    @php $configDom = $allDomains->firstWhere('id', $configDomainId); @endphp
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated" style="max-width:760px;">
            <div class="lp-modal-header">
                <h2>Configuración de {{ $configDom?->name }}</h2>
                <button wire:click="closeConfig" class="btn btn-ghost btn-sm" style="padding:0;width:32px;height:32px;justify-content:center;">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="lp-modal-body">
                <pre class="domain-config-preview">{{ $configContent }}</pre>
            </div>
            <div class="lp-modal-footer">
                <button wire:click="closeConfig" class="btn btn-ghost">Cerrar</button>
                <button wire:click="deployDomain({{ $configDomainId }})" class="btn btn-primary">Redesplegar</button>
            </div>
        </div>
    </div>
    @endif

    @if($healthDomainId)
    @php $healthDom = $allDomains->firstWhere('id', $healthDomainId); @endphp
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated" style="max-width:480px;">
            <div class="lp-modal-header">
                <h2>Comprobación de {{ $healthDom?->name }}</h2>
                <button wire:click="closeHealth" class="btn btn-ghost btn-sm" style="padding:0;width:32px;height:32px;justify-content:center;">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="lp-modal-body">
                @if(!empty($healthResult['error']))
                <div class="alert alert-danger" style="margin-bottom:0;">
                    <i class="fa-solid fa-circle-exclamation"></i> {{ $healthResult['error'] }}
                </div>
                @else
                <div class="domain-health-detail {{ !empty($healthResult['ok']) ? 'is-ok' : 'is-bad' }}">
                    <i class="fa-solid {{ !empty($healthResult['ok']) ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i>
                    <div>
                        <div style="font-weight:700;">HTTP {{ $healthResult['status'] ?? '—' }}</div>
                        <div style="font-size:12px;color:var(--text-muted);">{{ $healthResult['url'] ?? '' }}</div>
                    </div>
                </div>
                <div class="domain-health-rows">
                    <div><span>URL final</span><strong>{{ $healthResult['finalUrl'] ?? '—' }}</strong></div>
                    <div><span>Redirección</span><strong>{{ !empty($healthResult['redirected']) ? 'Sí' : 'No' }}</strong></div>
                    <div><span>Días de SSL</span><strong>{{ $healthResult['tlsDays'] ?? '—' }}</strong></div>
                </div>
                @endif
            </div>
            <div class="lp-modal-footer">
                <button wire:click="closeHealth" class="btn btn-ghost" style="flex:1;justify-content:center;">Cerrar</button>
            </div>
        </div>
    </div>
    @endif

    <div wire:loading.delay class="lp-loading-toast">
        <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
    </div>
</div>
