<div>
    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">
                <i class="fa-solid fa-shield-halved" style="color:{{ $ufwEnabled ? 'var(--success)' : 'var(--danger)' }};margin-right:10px;"></i>
                Firewall — UFW
            </h1>
            <p style="color:var(--text-secondary);font-size:13px;">Gestión de reglas de entrada y salida con Uncomplicated Firewall (UFW).</p>
        </div>

        {{-- UFW toggle --}}
        <div style="display:flex;gap:8px;align-items:center;">
            @if($rules->count() === 0)
            <button wire:click="installDefaults" class="btn btn-ghost btn-sm" style="color:var(--accent-light);">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Instalar reglas esenciales
            </button>
            @endif
            @if($ufwEnabled)
            <button wire:click="disableFirewall" class="btn btn-ghost btn-sm" style="color:var(--warning);" onclick="return confirm('¿Desactivar el firewall? El servidor quedará expuesto.')">
                <i class="fa-solid fa-shield-slash"></i> Desactivar UFW
            </button>
            @else
            <button wire:click="enableFirewall" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-shield-check"></i> Activar UFW
            </button>
            @endif
        </div>
    </div>

    {{-- Alerts --}}
    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    {{-- Status Cards --}}
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px;">
        {{-- UFW State --}}
        <div class="glass" style="padding:14px;text-align:center;border-color:{{ $ufwEnabled ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)' }};">
            <i class="fa-solid fa-{{ $ufwEnabled ? 'shield-check' : 'shield-slash' }}" style="font-size:24px;color:{{ $ufwEnabled ? 'var(--success)' : 'var(--danger)' }};margin-bottom:8px;display:block;"></i>
            <div style="font-size:12px;font-weight:700;color:{{ $ufwEnabled ? 'var(--success)' : 'var(--danger)' }};">{{ $ufwEnabled ? 'ACTIVO' : 'INACTIVO' }}</div>
            <div style="font-size:10px;color:var(--text-muted);">UFW</div>
        </div>
        {{-- Default IN --}}
        <div class="glass" style="padding:14px;text-align:center;">
            <div style="font-size:20px;font-weight:800;color:{{ $status['default_in'] === 'deny' ? 'var(--success)' : 'var(--danger)' }};">{{ strtoupper($status['default_in']) }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Default Entrada</div>
        </div>
        {{-- Default OUT --}}
        <div class="glass" style="padding:14px;text-align:center;">
            <div style="font-size:20px;font-weight:800;color:var(--success);">{{ strtoupper($status['default_out']) }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Default Salida</div>
        </div>
        {{-- Established connections --}}
        <div class="glass" style="padding:14px;text-align:center;">
            <div style="font-size:20px;font-weight:800;color:var(--accent-light);">{{ $connStats['established'] }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Conexiones Est.</div>
        </div>
        {{-- Active rules --}}
        <div class="glass" style="padding:14px;text-align:center;">
            <div style="font-size:20px;font-weight:800;color:var(--text-primary);">{{ $rules->where('is_active', true)->count() }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Reglas Activas</div>
        </div>
    </div>

    {{-- UFW Warning Banner --}}
    @if(!$ufwEnabled)
    <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
        <i class="fa-solid fa-triangle-exclamation" style="color:var(--danger);font-size:20px;flex-shrink:0;"></i>
        <div>
            <strong style="color:var(--danger);">Firewall Desactivado</strong>
            <p style="font-size:12px;color:var(--text-secondary);margin-top:2px;">El servidor está expuesto. Configura tus reglas y activa UFW lo antes posible.</p>
        </div>
        <button wire:click="enableFirewall" class="btn btn-danger btn-sm" style="margin-left:auto;flex-shrink:0;">
            <i class="fa-solid fa-shield-check"></i> Activar ahora
        </button>
    </div>
    @endif

    {{-- Tabs --}}
    <div style="display:flex;gap:6px;margin-bottom:20px;border-bottom:1px solid var(--glass-border);padding-bottom:12px;">
        <button wire:click="$set('activeTab','rules')" class="btn {{ $activeTab === 'rules' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-list-check"></i> Reglas ({{ $rules->count() }})
        </button>
        <button wire:click="$set('activeTab','presets')" class="btn {{ $activeTab === 'presets' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-layer-group"></i> Presets
        </button>
        <button wire:click="$set('activeTab','raw')" class="btn {{ $activeTab === 'raw' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
            <i class="fa-solid fa-terminal"></i> UFW Status Raw
        </button>
        <div style="margin-left:auto;">
            <button wire:click="$set('confirmReset', true)" class="btn btn-ghost btn-sm" style="color:var(--danger);">
                <i class="fa-solid fa-rotate-left"></i> Reset
            </button>
        </div>
    </div>

    {{-- TAB: Rules --}}
    @if($activeTab === 'rules')
    <div style="display:grid;grid-template-columns:1fr 2.5fr;gap:16px;align-items:start;">

        {{-- Add Rule Form --}}
        <div class="glass" style="padding:22px;">
            <h2 style="font-size:14px;font-weight:700;margin-bottom:14px;">
                <i class="fa-solid fa-plus" style="color:var(--accent-light);margin-right:6px;"></i>
                Nueva Regla
            </h2>

            <div class="form-group">
                <label class="form-label" style="font-size:11px;">Nombre / Descripción</label>
                <input type="text" wire:model="ruleName" class="form-input" placeholder="ej. Acceso DB desde oficina" style="font-size:12px;">
                @error('ruleName') <div style="font-size:10px;color:var(--danger);margin-top:3px;">{{ $message }}</div> @enderror
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">Acción</label>
                    <select wire:model="ruleAction" class="form-input" style="font-size:12px;">
                        <option value="allow">✅ Allow</option>
                        <option value="deny">🚫 Deny</option>
                        <option value="reject">❌ Reject</option>
                        <option value="limit">⚠️ Limit</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">Dirección</label>
                    <select wire:model="ruleDirection" class="form-input" style="font-size:12px;">
                        <option value="in">↓ Entrada</option>
                        <option value="out">↑ Salida</option>
                        <option value="any">↕ Ambas</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:8px;margin-bottom:12px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">Puerto <span style="color:var(--text-muted);">(rango: 8000:8100)</span></label>
                    <input type="text" wire:model="rulePort" class="form-input" placeholder="80, 443, 3306" style="font-family:monospace;font-size:12px;">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:11px;">Protocolo</label>
                    <select wire:model="ruleProtocol" class="form-input" style="font-size:12px;">
                        <option value="tcp">TCP</option>
                        <option value="udp">UDP</option>
                        <option value="any">Any</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-size:11px;">IP Origen <span style="color:var(--text-muted);">(vacío = cualquier IP)</span></label>
                <input type="text" wire:model="ruleSourceIp" class="form-input" placeholder="192.168.1.0/24 o 1.2.3.4" style="font-family:monospace;font-size:12px;">
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label" style="font-size:11px;">Notas — opcional</label>
                <input type="text" wire:model="ruleNotes" class="form-input" placeholder="Descripción de por qué existe esta regla" style="font-size:12px;">
            </div>

            {{-- Action preview --}}
            <div style="background:rgba(0,0,0,0.2);border-radius:6px;padding:8px;margin-bottom:12px;font-size:11px;font-family:monospace;color:var(--accent-light);">
                $ ufw {{ $ruleAction }} {{ $ruleDirection !== 'any' ? $ruleDirection : '' }}
                {{ $ruleSourceIp ? 'from '.$ruleSourceIp.' to any' : '' }}
                {{ $rulePort ? 'port '.$rulePort : '' }}
                {{ $ruleProtocol !== 'any' ? 'proto '.$ruleProtocol : '' }}
            </div>

            <button wire:click="addRule" class="btn btn-primary" style="width:100%;justify-content:center;" wire:loading.attr="disabled">
                <span wire:loading.remove><i class="fa-solid fa-plus-circle"></i> Añadir Regla</span>
                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Aplicando...</span>
            </button>
        </div>

        {{-- Rules Table --}}
        <div class="glass" style="padding:20px;">
            <h2 style="font-size:14px;font-weight:700;margin-bottom:14px;">
                <i class="fa-solid fa-list-check" style="color:var(--accent-light);margin-right:6px;"></i>
                Reglas Configuradas
            </h2>

            @if($rules->isEmpty())
            <div style="text-align:center;padding:50px 20px;color:var(--text-muted);">
                <i class="fa-solid fa-shield" style="font-size:40px;opacity:0.2;margin-bottom:14px;display:block;"></i>
                <p style="font-size:13px;">Sin reglas configuradas.</p>
                <button wire:click="installDefaults" class="btn btn-primary btn-sm" style="margin-top:12px;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Instalar reglas esenciales
                </button>
            </div>
            @else
            <div style="overflow-x:auto;">
                <table class="table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Regla</th>
                            <th>Acción</th>
                            <th>Dir</th>
                            <th>Puerto</th>
                            <th>IP Origen</th>
                            <th>Estado</th>
                            <th style="text-align:right;">Ops</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rules as $rule)
                        <tr style="opacity:{{ $rule->is_active ? '1' : '0.5' }};">
                            <td>
                                <div style="font-weight:600;font-size:13px;color:var(--text-primary);">{{ $rule->name }}</div>
                                @if($rule->notes)
                                <div style="font-size:10px;color:var(--text-muted);">{{ $rule->notes }}</div>
                                @endif
                                @if($rule->is_preset)
                                <span style="font-size:9px;background:rgba(99,102,241,0.15);color:var(--accent-light);border-radius:4px;padding:1px 5px;">PRESET</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $rule->actionBadgeClass() }}" style="font-size:11px;font-weight:700;font-family:monospace;">
                                    {{ strtoupper($rule->action) }}
                                </span>
                            </td>
                            <td>
                                <i class="fa-solid {{ $rule->directionIcon() }}" style="color:var(--text-muted);"></i>
                                <span style="font-size:11px;color:var(--text-muted);">{{ $rule->direction }}</span>
                            </td>
                            <td>
                                <code style="font-size:12px;font-family:monospace;color:var(--accent-light);">{{ $rule->portDisplay() }}</code>
                            </td>
                            <td>
                                <code style="font-size:11px;color:var(--text-secondary);">{{ $rule->source_ip ?? 'Cualquiera' }}</code>
                            </td>
                            <td>
                                <span class="badge {{ $rule->is_active ? 'badge-success' : 'badge-muted' }}" style="font-size:10px;cursor:pointer;" wire:click="toggleRule({{ $rule->id }})">
                                    {{ $rule->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:4px;">
                                    <button wire:click="toggleRule({{ $rule->id }})" class="btn btn-ghost btn-sm" title="{{ $rule->is_active ? 'Desactivar' : 'Activar' }}">
                                        <i class="fa-solid fa-{{ $rule->is_active ? 'pause' : 'play' }}" style="color:var(--{{ $rule->is_active ? 'warning' : 'success' }});"></i>
                                    </button>
                                    @if(!$rule->is_preset)
                                    <button wire:click="deleteRule({{ $rule->id }})" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar esta regla del firewall?')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:12px;font-size:11px;color:var(--text-muted);border-top:1px solid var(--glass-border);padding-top:10px;">
                <i class="fa-solid fa-circle-info" style="margin-right:4px;"></i>
                Las reglas se aplican en orden. UFW procesa la primera coincidencia.
                Las reglas con etiqueta <span style="color:var(--accent-light);">PRESET</span> no pueden eliminarse.
            </div>
            @endif
        </div>
    </div>

    {{-- TAB: Presets --}}
    @elseif($activeTab === 'presets')
    <div class="glass" style="padding:22px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
            <div>
                <h2 style="font-size:15px;font-weight:700;margin-bottom:4px;">
                    <i class="fa-solid fa-layer-group" style="color:var(--accent-light);margin-right:8px;"></i>
                    Presets de Firewall
                </h2>
                <p style="font-size:12px;color:var(--text-secondary);">Selecciona los servicios que tu servidor debe exponer y aplícalos de una vez.</p>
            </div>
            @if(!empty($selectedPresets))
            <button wire:click="applyPresets" class="btn btn-primary">
                <i class="fa-solid fa-shield-check"></i> Aplicar {{ count($selectedPresets) }} preset(s)
            </button>
            @endif
        </div>

        @php
            $presetGroups = [
                'Web'   => ['http','https'],
                'Email' => ['smtp','smtps','submission','imap','imaps','pop3','pop3s'],
                'Acceso'=> ['ssh','ssh_limit'],
                'DNS'   => ['dns_udp','dns_tcp'],
                'Datos' => ['mysql','redis'],
                'Panel' => ['ftp','ftp_data','rspamd','pdns'],
            ];
        @endphp

        @foreach($presetGroups as $group => $keys)
        <div style="margin-bottom:20px;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);margin-bottom:10px;">{{ $group }}</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;">
                @foreach($keys as $key)
                @php $preset = $presets[$key]; $selected = in_array($key, $selectedPresets); @endphp
                @php
                    // Check if already installed
                    $already = \App\Models\FirewallRule::where('user_id', auth()->id())->where('port', $preset['port'])->where('protocol', $preset['protocol'])->exists();
                @endphp
                <button wire:click="togglePreset('{{ $key }}')"
                    style="text-align:left;padding:12px 14px;border-radius:10px;border:1px solid {{ $selected ? 'rgba(99,102,241,0.5)' : 'var(--glass-border)' }};background:{{ $selected ? 'rgba(99,102,241,0.12)' : 'rgba(255,255,255,0.03)' }};cursor:{{ $already ? 'default' : 'pointer' }};transition:all 0.15s;opacity:{{ $already ? '0.5' : '1' }};">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                        <strong style="font-size:13px;color:var(--text-primary);">{{ $preset['name'] }}</strong>
                        <div style="display:flex;gap:4px;align-items:center;">
                            <span class="badge {{ $preset['action'] === 'allow' ? 'badge-success' : 'badge-danger' }}" style="font-size:9px;">{{ strtoupper($preset['action']) }}</span>
                            @if($already)
                            <span style="font-size:9px;color:var(--success);"><i class="fa-solid fa-check"></i></span>
                            @elseif($selected)
                            <span style="font-size:12px;color:var(--accent-light);"><i class="fa-solid fa-check-circle"></i></span>
                            @endif
                        </div>
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);">
                        <code>{{ $preset['port'] }}/{{ strtoupper($preset['protocol']) }}</code>
                        — {{ $preset['notes'] }}
                    </div>
                </button>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- TAB: Raw --}}
    @elseif($activeTab === 'raw')
    <div class="glass" style="padding:22px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <h2 style="font-size:14px;font-weight:700;margin:0;">
                <i class="fa-solid fa-terminal" style="color:var(--accent-light);margin-right:8px;"></i>
                Salida de <code>ufw status verbose</code>
            </h2>
            <button wire:click="$refresh" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-rotate"></i> Actualizar
            </button>
        </div>
        <pre style="background:rgba(0,0,0,0.4);border:1px solid var(--glass-border);border-radius:8px;padding:16px;font-family:monospace;font-size:12px;color:#a6e3a1;line-height:1.7;white-space:pre-wrap;overflow-x:auto;margin:0;">{{ $status['raw_output'] }}</pre>
    </div>
    @endif

    {{-- Reset Confirm Modal --}}
    @if($confirmReset)
    <div style="position:fixed;inset:0;z-index:300;background:rgba(0,0,0,0.8);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="max-width:440px;width:100%;padding:30px;margin:16px;border-color:rgba(239,68,68,0.4);">
            <div style="text-align:center;margin-bottom:20px;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size:40px;color:var(--danger);margin-bottom:12px;display:block;"></i>
                <h3 style="font-size:17px;font-weight:700;color:var(--danger);margin-bottom:8px;">Resetear Firewall</h3>
                <p style="font-size:13px;color:var(--text-secondary);line-height:1.6;">Esto eliminará <strong>todas las reglas UFW</strong> y reiniciará el firewall a valores por defecto. <strong>El acceso SSH (puerto 22) se re-aplicará automáticamente.</strong></p>
            </div>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button wire:click="$set('confirmReset',false)" class="btn btn-ghost">Cancelar</button>
                <button wire:click="resetFirewall" class="btn btn-danger">
                    <i class="fa-solid fa-rotate-left"></i> Sí, resetear
                </button>
            </div>
        </div>
    </div>
    @endif

    <div wire:loading style="position:fixed;bottom:24px;right:24px;z-index:300;">
        <div class="glass" style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-spinner fa-spin"></i> Aplicando en UFW...
        </div>
    </div>
</div>
