<div>
    {{-- Tab Navigation --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">
                <i class="fa-solid fa-shield-virus" style="color:var(--accent-light);margin-right:8px;"></i>
                Antispam — Rspamd
            </h1>
            <p style="color:var(--text-secondary);font-size:13px;">Monitoreo en tiempo real, reglas personalizadas y análisis de mensajes.</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div style="display:flex;gap:6px;margin-bottom:20px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;">
        <button wire:click="$set('activeTab','dashboard')" class="btn {{ $activeTab === 'dashboard' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-chart-line"></i> Dashboard
        </button>
        <button wire:click="$set('activeTab','rules')" class="btn {{ $activeTab === 'rules' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-list-check"></i> Reglas ({{ $rules->count() }})
        </button>
        <button wire:click="$set('activeTab','test')" class="btn {{ $activeTab === 'test' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-flask"></i> Probar Mensaje
        </button>
    </div>

    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:16px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    {{-- TAB: Dashboard --}}
    @if($activeTab === 'dashboard')
    {{-- Stats Cards --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
        <div class="glass" style="padding:18px;text-align:center;">
            <div style="font-size:28px;font-weight:800;color:var(--text-primary);">{{ number_format($stats['scanned']) }}</div>
            <div style="font-size:11px;color:var(--text-secondary);">Mensajes Escaneados</div>
        </div>
        <div class="glass" style="padding:18px;text-align:center;">
            <div style="font-size:28px;font-weight:800;color:var(--danger);">{{ number_format($stats['spam_count']) }}</div>
            <div style="font-size:11px;color:var(--text-secondary);">Spam Detectado</div>
            <div style="font-size:10px;color:var(--danger);margin-top:2px;">{{ $stats['spam_percentage'] }}% del total</div>
        </div>
        <div class="glass" style="padding:18px;text-align:center;">
            <div style="font-size:28px;font-weight:800;color:var(--success);">{{ number_format($stats['ham_count']) }}</div>
            <div style="font-size:11px;color:var(--text-secondary);">Ham (Legítimos)</div>
        </div>
        <div class="glass" style="padding:18px;text-align:center;">
            <div style="font-size:28px;font-weight:800;color:var(--accent-light);">{{ number_format($stats['learned']) }}</div>
            <div style="font-size:11px;color:var(--text-secondary);">Muestras Aprendidas</div>
        </div>
    </div>

    {{-- Actions breakdown --}}
    @if(!empty($stats['actions']))
    <div class="glass" style="padding:20px;margin-bottom:20px;">
        <h2 style="font-size:14px;font-weight:700;margin-bottom:14px;">Acciones Tomadas</h2>
        @php $totalActions = array_sum($stats['actions']); @endphp
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($stats['actions'] as $action => $count)
            @php
                $pct = $totalActions > 0 ? round(($count / $totalActions) * 100, 1) : 0;
                $color = match(true) {
                    str_contains($action,'reject')     => 'var(--danger)',
                    str_contains($action,'add header') => 'var(--warning)',
                    str_contains($action,'rewrite')    => 'var(--accent-light)',
                    default                            => 'var(--success)',
                };
            @endphp
            <div>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:12px;">
                    <span style="text-transform:capitalize;">{{ $action }}</span>
                    <span style="color:var(--text-muted);">{{ number_format($count) }} ({{ $pct }}%)</span>
                </div>
                <div style="height:6px;border-radius:3px;background:rgba(255,255,255,0.06);overflow:hidden;">
                    <div style="height:100%;width:{{ $pct }}%;background:{{ $color }};border-radius:3px;transition:width 0.5s;"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Recent History --}}
    @if(!empty($history))
    <div class="glass" style="padding:20px;">
        <h2 style="font-size:14px;font-weight:700;margin-bottom:14px;">Mensajes Recientes</h2>
        <div style="overflow-x:auto;">
            <table class="table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Asunto</th>
                        <th>Remitente</th>
                        <th>Score</th>
                        <th>Acción</th>
                        <th>Hora</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($history, 0, 20) as $msg)
                    <tr>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;">{{ $msg['subject'] ?? '(sin asunto)' }}</td>
                        <td style="font-size:11px;color:var(--text-secondary);">{{ $msg['from'] ?? '—' }}</td>
                        <td>
                            @php $score = $msg['score'] ?? 0; @endphp
                            <span style="font-size:12px;font-weight:700;color:{{ $score > 10 ? 'var(--danger)' : ($score > 4 ? 'var(--warning)' : 'var(--success)') }};">
                                {{ number_format($score, 1) }}
                            </span>
                        </td>
                        <td>
                            @php $action = $msg['action'] ?? 'no action'; @endphp
                            <span class="badge {{ str_contains($action,'reject') ? 'badge-danger' : (str_contains($action,'header') || str_contains($action,'rewrite') ? 'badge-warning' : 'badge-success') }}" style="font-size:10px;">
                                {{ $action }}
                            </span>
                        </td>
                        <td style="font-size:11px;color:var(--text-muted);">{{ isset($msg['time']) ? date('H:i:s', $msg['time']) : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- TAB: Rules --}}
    @elseif($activeTab === 'rules')
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;align-items:start;">
        {{-- Add Rule Form --}}
        <div class="glass" style="padding:22px;">
            <h2 style="font-size:14px;font-weight:700;margin-bottom:14px;">Nueva Regla</h2>

            <div class="form-group">
                <label class="form-label">Tipo</label>
                <select wire:model.live="ruleType" class="form-input" style="font-size:12px;">
                    <option value="whitelist_ip">✅ IP Permitida</option>
                    <option value="whitelist_email">✅ Email Permitido</option>
                    <option value="whitelist_domain">✅ Dominio Permitido</option>
                    <option value="blacklist_ip">🚫 IP Bloqueada</option>
                    <option value="blacklist_email">🚫 Email Bloqueado</option>
                    <option value="blacklist_domain">🚫 Dominio Bloqueado</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    {{ str_contains($ruleType, 'ip') ? 'Dirección IP' : (str_contains($ruleType, 'email') ? 'Email' : 'Dominio') }}
                </label>
                <input type="text" wire:model="ruleValue" class="form-input" style="font-size:12px;" placeholder="{{ str_contains($ruleType, 'ip') ? '1.2.3.4 o 1.2.3.0/24' : (str_contains($ruleType, 'email') ? 'user@domain.com' : 'example.com') }}">
                @error('ruleValue') <div style="font-size:11px;color:var(--danger);margin-top:4px;">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Acción</label>
                <select wire:model="ruleAction" class="form-input" style="font-size:12px;">
                    <option value="skip">Permitir (skip antispam)</option>
                    <option value="reject">Rechazar automáticamente</option>
                    <option value="score_add">Ajustar puntuación</option>
                </select>
            </div>

            @if($ruleAction === 'score_add')
            <div class="form-group">
                <label class="form-label">Modificador de puntuación</label>
                <input type="number" wire:model="ruleScore" class="form-input" step="0.5" min="-10" max="20" placeholder="-5 (reduce spam score)">
            </div>
            @endif

            <div class="form-group">
                <label class="form-label">Notas <span style="color:var(--text-muted);font-weight:400;">— opcional</span></label>
                <input type="text" wire:model="ruleNotes" class="form-input" style="font-size:12px;">
            </div>

            <button wire:click="addRule" class="btn btn-primary" style="width:100%;justify-content:center;" wire:loading.attr="disabled">
                <i class="fa-solid fa-plus-circle"></i> Agregar Regla
            </button>
        </div>

        {{-- Rules List --}}
        <div class="glass" style="padding:20px;">
            <h2 style="font-size:14px;font-weight:700;margin-bottom:14px;">Reglas Activas ({{ $rules->count() }})</h2>
            @if($rules->isEmpty())
            <div style="text-align:center;padding:40px;color:var(--text-muted);">Sin reglas configuradas.</div>
            @else
            <div style="overflow-x:auto;">
                <table class="table" style="width:100%;">
                    <thead>
                        <tr><th>Tipo</th><th>Valor</th><th>Acción</th><th>Estado</th><th style="text-align:right;">Ops</th></tr>
                    </thead>
                    <tbody>
                        @foreach($rules as $rule)
                        <tr>
                            <td>
                                <span class="badge {{ $rule->isWhitelist() ? 'badge-success' : 'badge-danger' }}" style="font-size:10px;">
                                    {{ $rule->typeLabel() }}
                                </span>
                            </td>
                            <td><code style="font-size:12px;">{{ $rule->value }}</code></td>
                            <td><span style="font-size:11px;color:var(--text-secondary);">{{ ucfirst($rule->action) }}</span></td>
                            <td>
                                <span class="badge {{ $rule->is_active ? 'badge-success' : 'badge-muted' }}" style="font-size:10px;cursor:pointer;" wire:click="toggleRule({{ $rule->id }})">
                                    {{ $rule->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <button wire:click="deleteRule({{ $rule->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar esta regla?')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- TAB: Test --}}
    @elseif($activeTab === 'test')
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="glass" style="padding:22px;">
            <h2 style="font-size:14px;font-weight:700;margin-bottom:14px;">Escanear Mensaje</h2>
            <p style="font-size:12px;color:var(--text-secondary);margin-bottom:14px;">Pega el contenido raw de un email (incluyendo headers) para analizarlo con Rspamd.</p>
            <textarea wire:model="testMessage" class="form-input" style="font-family:monospace;font-size:11px;height:250px;resize:vertical;" placeholder="From: sender@example.com&#10;To: recipient@example.com&#10;Subject: Test message&#10;&#10;This is the email body..."></textarea>
            <button wire:click="testScan" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:12px;" wire:loading.attr="disabled">
                <span wire:loading.remove><i class="fa-solid fa-flask"></i> Analizar con Rspamd</span>
                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Escaneando...</span>
            </button>
        </div>

        <div class="glass" style="padding:22px;">
            <h2 style="font-size:14px;font-weight:700;margin-bottom:14px;">Resultado del Análisis</h2>
            @if($testResult)
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
                <div style="background:rgba(0,0,0,0.2);border-radius:8px;padding:12px;text-align:center;">
                    <div style="font-size:26px;font-weight:800;color:{{ ($testResult['score'] ?? 0) > 8 ? 'var(--danger)' : (($testResult['score'] ?? 0) > 4 ? 'var(--warning)' : 'var(--success)') }};">
                        {{ number_format($testResult['score'] ?? 0, 2) }}
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);">Spam Score</div>
                </div>
                <div style="background:rgba(0,0,0,0.2);border-radius:8px;padding:12px;text-align:center;">
                    <span class="badge {{ ($testResult['is_spam'] ?? false) ? 'badge-danger' : 'badge-success' }}" style="font-size:14px;padding:8px 12px;">
                        {{ ($testResult['is_spam'] ?? false) ? '🚫 SPAM' : '✅ LIMPIO' }}
                    </span>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:6px;">{{ $testResult['action'] ?? '' }}</div>
                </div>
            </div>

            @if(!empty($testResult['symbols']))
            <h3 style="font-size:12px;font-weight:700;margin-bottom:10px;">Reglas Disparadas:</h3>
            <div style="max-height:200px;overflow-y:auto;">
                @foreach($testResult['symbols'] as $symbol => $data)
                <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--glass-border);font-size:11px;">
                    <code>{{ $symbol }}</code>
                    <span style="color:{{ ($data['score'] ?? 0) > 0 ? 'var(--danger)' : 'var(--success)' }};font-weight:600;">{{ number_format($data['score'] ?? 0, 2) }}</span>
                </div>
                @endforeach
            </div>
            @endif

            @if(isset($testResult['message']))
            <div style="margin-top:12px;font-size:11px;color:var(--text-muted);background:rgba(0,0,0,0.2);padding:8px;border-radius:6px;">{{ $testResult['message'] }}</div>
            @endif
            @else
            <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
                <i class="fa-solid fa-flask" style="font-size:40px;opacity:0.2;margin-bottom:12px;display:block;"></i>
                Ingresa un mensaje y pulsa "Analizar" para ver los resultados.
            </div>
            @endif
        </div>
    </div>
    @endif

    <div wire:loading style="position:fixed;bottom:24px;right:24px;z-index:300;">
        <div class="glass" style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
        </div>
    </div>
</div>
