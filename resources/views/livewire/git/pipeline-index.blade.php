<div class="lp-split" style="display:grid;grid-template-columns:260px minmax(0,1fr);gap:24px;align-items:start;">
    {{-- Sidebar: pipeline list --}}
    <div class="glass lp-panel" style="padding:16px;">
        <div style="display:flex;align-items:center;flex-wrap:wrap;justify-content:space-between;gap:8px;margin-bottom:16px;">
            <h2 class="panel-title" style="margin:0;">Pipelines</h2>
            <button wire:click="createNew" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i></button>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($pipelines as $pipe)
            <button wire:click="selectPipeline({{ $pipe->id }})"
                style="width:100%;text-align:left;padding:12px;border-radius:8px;border:1px solid {{ ($selectedPipeline && $selectedPipeline->id === $pipe->id && ! $isCreating) ? 'rgba(99,102,241,0.5)' : 'var(--glass-border)' }};background:{{ ($selectedPipeline && $selectedPipeline->id === $pipe->id && ! $isCreating) ? 'rgba(99,102,241,0.1)' : 'rgba(255,255,255,0.03)' }};cursor:pointer;transition:all 0.2s;">
                <div style="font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:4px;overflow-wrap:anywhere;">{{ $pipe->name }}</div>
                <div style="font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:6px;">
                    <i class="fa-solid fa-diagram-project"></i> {{ $pipe->stageCount() }} etapas
                    @if($pipe->gitDeployment)
                        <span>· {{ $pipe->gitDeployment->domain_name }}</span>
                    @endif
                </div>
                <div style="margin-top:6px;">
                    @if($pipe->last_run_status)
                        <span class="badge {{ $pipe->statusBadgeClass() }}" style="font-size:9px;">{{ strtoupper($pipe->last_run_status) }}</span>
                    @else
                        <span class="badge badge-muted" style="font-size:9px;">SIN EJECUTAR</span>
                    @endif
                    <span style="font-size:10px;color:var(--text-muted);margin-left:6px;">{{ $pipe->last_run_at?->diffForHumans() ?? '—' }}</span>
                </div>
            </button>
            @endforeach

            @if($pipelines->isEmpty())
            <div style="text-align:center;padding:20px 10px;color:var(--text-muted);font-size:12px;">
                No hay pipelines configurados.
            </div>
            @endif
        </div>
    </div>

    {{-- Main content --}}
    <div>
        @if(session()->has('message'))
        <div class="alert alert-success" style="margin-bottom:20px;">
            <i class="fa-solid fa-circle-check"></i> {{ session('message') }}
        </div>
        @endif
        @if(session()->has('error'))
        <div class="alert alert-danger" style="margin-bottom:20px;">
            <i class="fa-solid fa-circle-xmark"></i> {{ session('error') }}
        </div>
        @endif

        @if($isCreating || $selectedPipeline)
            <div class="glass lp-panel" style="padding:24px;">
                {{-- Header --}}
                <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:center;margin-bottom:20px;">
                    <h2 class="panel-title" style="font-size:18px;margin:0;overflow-wrap:anywhere;">
                        <i class="fa-solid fa-diagram-project" style="color:var(--accent-light);margin-right:8px;"></i>
                        {{ $isCreating ? 'Nuevo Pipeline' : $selectedPipeline->name }}
                    </h2>
                    @if(! $isCreating && $selectedPipeline)
                    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                        @if($selectedPipeline->last_run_at)
                        <span style="font-size:11px;color:var(--text-muted);"><i class="fa-regular fa-clock"></i> Último run: {{ $selectedPipeline->last_run_at->diffForHumans() }}</span>
                        @endif
                        <button wire:click="runPipeline" class="btn btn-primary btn-sm" wire:loading.attr="disabled" title="Ejecuta las etapas en el directorio de despliegue">
                            <span wire:loading.remove><i class="fa-solid fa-play"></i> Ejecutar ahora</span>
                            <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Ejecutando...</span>
                        </button>
                        <button wire:click="deletePipeline" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este pipeline? Se conservarán las ejecuciones anteriores.')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                    @endif
                </div>

                {{-- Streaming live log --}}
                <div id="pipeline-run-stream" @if($expandedRun && $expandedRun->status === 'running') style="display:block;" @else style="display:none;" @endif>
                    <div style="background:#0d1117;border:1px solid var(--glass-border);border-radius:8px;padding:14px;margin-bottom:20px;">
                        <div style="font-size:12px;color:var(--accent-light);margin-bottom:8px;"><i class="fa-solid fa-spinner fa-spin"></i> Ejecutando etapas en vivo...</div>
                        <pre wire:stream="pipeline-run-log" style="margin:0;font-family:'Courier New',Courier,monospace;font-size:12px;color:#c9d1d9;white-space:pre-wrap;word-wrap:break-word;line-height:1.5;max-height:340px;overflow:auto;"></pre>
                    </div>
                </div>

                {{-- Expanded run --}}
                @if($expandedRun)
                <div class="glass" style="padding:16px;margin-bottom:20px;border-radius:8px;">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;margin-bottom:14px;">
                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;min-width:0;">
                            <button wire:click="$set('expandedRun', null)" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Volver</button>
                            <span class="badge {{ $expandedRun->statusBadgeClass() }}">{{ strtoupper($expandedRun->status) }}</span>
                            <span style="font-size:12px;color:var(--text-muted);">#{{ $expandedRun->id }} · {{ ucfirst($expandedRun->triggered_by) }}</span>
                            <span style="font-size:12px;color:var(--text-muted);">{{ $expandedRun->created_at->format('Y-m-d H:i:s') }} · {{ $expandedRun->finished_at ? $expandedRun->created_at->diffForHumans($expandedRun->finished_at) : '' }}</span>
                        </div>
                        <span style="font-size:12px;color:var(--text-muted);"><i class="fa-solid fa-stopwatch"></i> {{ number_format($expandedRun->totalDurationMs(), 0, ',', '.') }} ms</span>
                    </div>

                    @forelse($expandedRun->output ?? [] as $stageResult)
                        @php $expanded = $this->expandedStage === ($stageResult['stage'] ?? ''); @endphp
                        <div style="border:1px solid var(--glass-border);border-radius:8px;margin-bottom:10px;overflow:hidden;">
                            <button wire:click="$set('expandedStage', {{ $expanded ? 'null' : "'{$stageResult['stage']}'" }})"
                                style="width:100%;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:8px;padding:12px 16px;text-align:left;background:{{ $stageResult['ok'] ? 'rgba(34,197,94,0.06)' : 'rgba(239,68,68,0.08)' }};border:none;cursor:pointer;">
                                <div style="display:flex;align-items:center;gap:10px;min-width:0;overflow-wrap:anywhere;">
                                    <i class="fa-solid {{ $stageResult['ok'] ? 'fa-circle-check' : 'fa-circle-xmark' }}" style="color:{{ $stageResult['ok'] ? 'var(--success)' : 'var(--danger)' }};"></i>
                                    <strong style="font-size:13px;color:var(--text-primary);">{{ $stageResult['name'] }}</strong>
                                    <span style="font-size:11px;color:var(--text-muted);">{{ $stageResult['stage'] }}</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <span style="font-size:11px;color:var(--text-muted);">{{ number_format($stageResult['duration_ms'], 0, ',', '.') }} ms</span>
                                    <i class="fa-solid {{ $expanded ? 'fa-chevron-up' : 'fa-chevron-down' }}" style="color:var(--text-muted);font-size:11px;"></i>
                                </div>
                            </button>
                            @if($expanded)
                            <pre style="margin:0;padding:14px 16px;background:rgba(0,0,0,0.4);font-family:monospace;font-size:11px;color:#cdd6f4;line-height:1.6;white-space:pre-wrap;word-break:break-word;max-height:320px;overflow:auto;border-top:1px solid var(--glass-border);">{{ $stageResult['log'] }}</pre>
                            @endif
                        </div>
                    @empty
                        <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px;">Esta ejecución no tiene salida registrada.</div>
                    @endforelse
                </div>
                @endif

                {{-- Editor form --}}
                <form wire:submit.prevent="save">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div class="form-group">
                            <label class="form-label">Nombre del Pipeline</label>
                            <input type="text" wire:model="name" class="form-input" placeholder="Ej: Build + Deploy producción">
                            @error('name') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">Despliegue Git asociado</label>
                            <select wire:model.live="git_deployment_id" class="form-input" style="appearance:none;">
                                <option value="">— Sin repositorio Git —</option>
                                @foreach($availableDeployments as $dep)
                                    <option value="{{ $dep->id }}">{{ $dep->domain_name }} ({{ $dep->branch }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;align-items:end;">
                        <div class="form-group">
                            <label class="form-label">Rama disparadora (Webhook)</label>
                            <input type="text" wire:model="branch" class="form-input" placeholder="main">
                            @error('branch') <div class="form-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group" style="display:flex;align-items:center;padding-top:28px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                                <input type="checkbox" wire:model="enable_webhook">
                                Ejecutar vía Webhook
                            </label>
                        </div>
                    </div>

                    {{-- Stages --}}
                    <label class="form-label" style="display:block;margin-bottom:10px;">Etapas del Pipeline</label>
                    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:20px;">
                        @foreach($stages as $index => $stage)
                        <div class="glass-elevated" style="padding:14px;border-radius:8px;">
                            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;">
                                <strong style="font-size:12px;color:var(--accent-light);"><i class="fa-solid fa-shoe-prints"></i> Etapa {{ $index + 1 }}</strong>
                                <div style="display:flex;gap:6px;">
                                    <button type="button" wire:click="moveStage({{ $index }}, -1)" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-up"></i></button>
                                    <button type="button" wire:click="moveStage({{ $index }}, 1)" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-down"></i></button>
                                    <button type="button" wire:click="removeStage({{ $index }})" class="btn btn-danger btn-sm"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;margin-bottom:10px;">
                                <div class="form-group">
                                    <label class="form-label">Nombre</label>
                                    <input type="text" wire:model="stages.{{ $index }}.name" class="form-input" placeholder="Ej: Compilar assets">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Comando principal</label>
                                    <input type="text" wire:model="stages.{{ $index }}.command" class="form-input" placeholder="composer install --no-interaction" style="font-family:monospace;font-size:12px;">
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div class="form-group">
                                    <label class="form-label">Script Bash adicional (multilínea)</label>
                                    <textarea wire:model="stages.{{ $index }}.script" rows="3" class="form-input" style="font-family:monospace;font-size:12px;line-height:1.6;" placeholder="{{ "php artisan migrate --force\nnpm run build" }}"></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Rama override / Variables (KEY=VALUE por línea)</label>
                                    <textarea wire:model="stages.{{ $index }}.env" rows="2" class="form-input" style="font-family:monospace;font-size:12px;line-height:1.6;" placeholder="{{ "APP_ENV=production\nNODE_ENV=production" }}"></textarea>
                                    <input type="text" wire:model="stages.{{ $index }}.branch_override" class="form-input" style="margin-top:8px;height:32px;font-size:12px;" placeholder="Rama override (vacío = rama del pipeline)">
                                </div>
                            </div>
                        </div>
                        @endforeach

                        <button type="button" wire:click="addStage" class="btn btn-ghost btn-sm">
                            <i class="fa-solid fa-plus"></i> Agregar etapa
                        </button>
                    </div>

                    <div style="display:flex;flex-wrap:wrap;justify-content:flex-end;gap:12px;">
                        <button type="button" wire:click="$set('isCreating', false)" class="btn btn-ghost">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar Pipeline</button>
                    </div>
                </form>
            </div>
        @else
            <div class="glass lp-panel" style="padding:clamp(24px,8vw,60px) 16px;text-align:center;">
                <i class="fa-solid fa-diagram-project" style="font-size:48px;opacity:0.2;margin-bottom:16px;display:block;"></i>
                <h3 class="panel-title" style="font-size:18px;margin-bottom:8px;">Pipeline de Despliegue</h3>
                <p style="color:var(--text-secondary);font-size:13px;max-width:min(460px,100%);margin:0 auto 24px auto;">
                    Encadena etapas (composer, npm, migraciones, scripts) que se ejecutan en el directorio del deploy Git,
                    con vista en vivo del progreso y resultados por etapa.
                </p>
                <button wire:click="createNew" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Crear Pipeline
                </button>
            </div>
        @endif
    </div>
</div>