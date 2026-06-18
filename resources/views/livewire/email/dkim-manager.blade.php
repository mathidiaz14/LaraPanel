<div>
    {{-- Email Submenu Navigation --}}
    @include('livewire.email._email-nav', ['active' => 'dkim'])

    <div class="page-header">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">Seguridad de Email: DKIM / SPF / DMARC</h1>
            <p style="color:var(--text-secondary);font-size:13px;">Configure autenticación de correo para mejorar la entregabilidad y prevenir el spoofing.</p>
        </div>
    </div>

    @if($successMessage)
    <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    <div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start;">

        {{-- Domain Selector --}}
        <div class="glass" style="padding:20px;">
            <h2 style="font-size:14px;font-weight:700;margin-bottom:14px;">Seleccionar Dominio</h2>
            @foreach($domains as $domain)
            @php $dkimKey = $dkimKeys->firstWhere('domain_id', $domain->id); @endphp
            <button wire:click="selectDomain({{ $domain->id }})"
                    style="width:100%;text-align:left;background:{{ $selectedDomainId === $domain->id ? 'rgba(99,102,241,0.15)' : 'rgba(255,255,255,0.04)' }};border:1px solid {{ $selectedDomainId === $domain->id ? 'rgba(99,102,241,0.4)' : 'var(--glass-border)' }};border-radius:8px;padding:10px 12px;cursor:pointer;margin-bottom:6px;display:flex;align-items:center;gap:8px;transition:all 0.2s;">
                <i class="fa-solid fa-globe" style="color:{{ $selectedDomainId === $domain->id ? 'var(--accent-light)' : 'var(--text-muted)' }};"></i>
                <div>
                    <div style="font-size:13px;font-weight:600;color:var(--text-primary);">{{ $domain->name }}</div>
                    @if($dkimKey)
                    <div style="font-size:10px;color:var(--success);margin-top:1px;"><i class="fa-solid fa-shield-check"></i> DKIM activo</div>
                    @else
                    <div style="font-size:10px;color:var(--text-muted);margin-top:1px;">Sin DKIM</div>
                    @endif
                </div>
            </button>
            @endforeach
        </div>

        @if($selectedDomain)
        {{-- DKIM/SPF/DMARC Panel --}}
        <div style="display:flex;flex-direction:column;gap:16px;">

            {{-- DKIM Card --}}
            <div class="glass" style="padding:22px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <div>
                        <h3 style="font-size:15px;font-weight:700;margin-bottom:2px;">
                            <i class="fa-solid fa-key" style="color:var(--accent-light);margin-right:8px;"></i>
                            DKIM Signing
                        </h3>
                        <p style="font-size:12px;color:var(--text-secondary);">Firma criptográfica RSA 2048-bit para todos los emails salientes.</p>
                    </div>
                    <button wire:click="generateDkim" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                        <span wire:loading.remove>
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                            {{ $dkimKeys->isNotEmpty() ? 'Regenerar DKIM' : 'Generar DKIM' }}
                        </span>
                        <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Generando...</span>
                    </button>
                </div>

                @if($dkimKeys->isNotEmpty())
                @php $activeKey = $dkimKeys->firstWhere('is_active', true); @endphp
                @if($activeKey)
                <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:8px;padding:14px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                        <span style="font-size:12px;font-weight:600;color:var(--success);"><i class="fa-solid fa-shield-check"></i> Clave DKIM Activa</span>
                        <span style="font-size:11px;color:var(--text-muted);">Selector: <code>{{ $activeKey->selector }}</code></span>
                    </div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-bottom:8px;">
                        <strong>Registro DNS:</strong> <code>{{ $activeKey->dnsRecordName() }}</code>
                    </div>
                    <div style="background:rgba(0,0,0,0.3);border-radius:6px;padding:8px;font-size:10px;font-family:monospace;color:var(--success);word-break:break-all;line-height:1.5;">
                        {{ $activeKey->dns_value }}
                    </div>
                    @if($activeKey->deployed_at)
                    <div style="font-size:11px;color:var(--text-muted);margin-top:8px;">
                        <i class="fa-solid fa-check-circle" style="color:var(--success);"></i>
                        Desplegado en DNS: {{ $activeKey->deployed_at->diffForHumans() }}
                    </div>
                    @endif
                </div>
                @endif
                @else
                <div style="background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.2);border-radius:8px;padding:12px;font-size:12px;color:var(--danger);">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Sin clave DKIM. Genera una usando el botón para mejorar la entregabilidad de emails.
                </div>
                @endif
            </div>

            {{-- SPF Card --}}
            <div class="glass" style="padding:22px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <div>
                        <h3 style="font-size:15px;font-weight:700;margin-bottom:2px;">
                            <i class="fa-solid fa-filter" style="color:var(--warning);margin-right:8px;"></i>
                            SPF (Sender Policy Framework)
                        </h3>
                        <p style="font-size:12px;color:var(--text-secondary);">Autoriza las IPs permitidas para enviar correo desde este dominio.</p>
                    </div>
                    <button wire:click="addSpfRecord" class="btn btn-ghost btn-sm" style="color:var(--warning);" wire:loading.attr="disabled">
                        <i class="fa-solid fa-plus"></i> Agregar SPF
                    </button>
                </div>
                @if($dnsZone)
                @php $spfRecord = $dnsZone->records->where('type','TXT')->where('name','@')->first(fn($r) => str_contains($r->content, 'v=spf1')); @endphp
                @if($spfRecord)
                <div style="background:rgba(16,185,129,0.07);border:1px solid rgba(16,185,129,0.2);border-radius:8px;padding:10px 14px;font-size:11px;font-family:monospace;color:var(--success);">
                    {{ $spfRecord->content }}
                </div>
                @else
                <div style="font-size:12px;color:var(--text-muted);">Sin registro SPF en la zona DNS. Usa el botón para agregar uno optimizado.</div>
                @endif
                @else
                <div style="font-size:12px;color:var(--text-muted);">Crea primero una zona DNS para {{ $selectedDomain->name }} en el módulo DNS Manager.</div>
                @endif
            </div>

            {{-- DMARC Card --}}
            <div class="glass" style="padding:22px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <div>
                        <h3 style="font-size:15px;font-weight:700;margin-bottom:2px;">
                            <i class="fa-solid fa-shield-halved" style="color:var(--success);margin-right:8px;"></i>
                            DMARC
                        </h3>
                        <p style="font-size:12px;color:var(--text-secondary);">Define qué hacer cuando los emails fallan la autenticación DKIM/SPF.</p>
                    </div>
                    <button wire:click="addDmarcRecord" class="btn btn-ghost btn-sm" style="color:var(--success);">
                        <i class="fa-solid fa-plus"></i> Aplicar DMARC
                    </button>
                </div>

                {{-- Policy selector --}}
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:14px;">
                    @foreach(['none' => ['fa-eye','var(--accent-light)','Monitoreo','Sin acción, solo reportes'], 'quarantine' => ['fa-box','var(--warning)','Cuarentena','Va a spam si falla'], 'reject' => ['fa-ban','var(--danger)','Rechazar','Bloqueo total si falla']] as $policy => [$icon, $color, $label, $desc])
                    <label style="cursor:pointer;">
                        <input type="radio" wire:model="dmarcPolicy" value="{{ $policy }}" style="display:none;">
                        <div style="padding:10px;border-radius:8px;text-align:center;border:1px solid {{ $dmarcPolicy === $policy ? $color : 'var(--glass-border)' }};background:{{ $dmarcPolicy === $policy ? 'rgba(99,102,241,0.08)' : 'transparent' }};transition:all 0.2s;cursor:pointer;">
                            <i class="fa-solid {{ $icon }}" style="font-size:16px;color:{{ $color }};margin-bottom:4px;display:block;"></i>
                            <div style="font-size:11px;font-weight:600;color:var(--text-primary);">{{ $label }}</div>
                            <div style="font-size:10px;color:var(--text-muted);">{{ $desc }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>

                @if($dnsZone)
                @php $dmarcRecord = $dnsZone->records->where('type','TXT')->where('name','_dmarc')->first(); @endphp
                @if($dmarcRecord)
                <div style="background:rgba(16,185,129,0.07);border:1px solid rgba(16,185,129,0.2);border-radius:8px;padding:10px 14px;font-size:11px;font-family:monospace;color:var(--success);">{{ $dmarcRecord->content }}</div>
                @else
                <div style="font-size:12px;color:var(--text-muted);">Sin registro DMARC aún.</div>
                @endif
                @endif
            </div>

            {{-- Verification --}}
            <div class="glass" style="padding:22px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h3 style="font-size:15px;font-weight:700;margin:0;">
                        <i class="fa-solid fa-magnifying-glass" style="color:var(--accent-light);margin-right:8px;"></i>
                        Verificar en DNS Público
                    </h3>
                    <button wire:click="verifyRecords" class="btn btn-ghost btn-sm" wire:loading.attr="disabled">
                        <span wire:loading.remove><i class="fa-solid fa-rotate"></i> Verificar Ahora</span>
                        <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Consultando DNS...</span>
                    </button>
                </div>

                @if(!empty($verifyResults))
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    @foreach($verifyResults as $type => $result)
                    <div style="background:rgba(0,0,0,0.2);border:1px solid {{ $result['status'] === 'ok' ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)' }};border-radius:10px;padding:14px;text-align:center;">
                        <i class="fa-solid {{ $result['status'] === 'ok' ? 'fa-circle-check' : 'fa-circle-xmark' }}" style="font-size:24px;color:{{ $result['status'] === 'ok' ? 'var(--success)' : 'var(--danger)' }};margin-bottom:8px;display:block;"></i>
                        <div style="font-size:13px;font-weight:700;text-transform:uppercase;margin-bottom:4px;">{{ $type }}</div>
                        <div style="font-size:11px;color:{{ $result['status'] === 'ok' ? 'var(--success)' : 'var(--danger)' }};">
                            {{ match($result['status']) { 'ok' => 'Publicado ✓', 'missing' => 'No encontrado', 'error' => 'Error DNS', 'not_configured' => 'No configurado', default => ucfirst($result['status']) } }}
                        </div>
                        @if($result['value'])
                        <div style="font-size:10px;color:var(--text-muted);margin-top:6px;word-break:break-all;text-align:left;background:rgba(0,0,0,0.2);border-radius:4px;padding:4px;">{{ Str::limit($result['value'], 80) }}</div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @else
                <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:12px;">
                    Usa el botón "Verificar Ahora" para comprobar que los registros están publicados en los DNS públicos.
                </div>
                @endif
            </div>

        </div>
        @else
        <div class="glass" style="padding:60px;text-align:center;">
            <i class="fa-solid fa-arrow-left" style="font-size:32px;opacity:0.2;margin-bottom:12px;display:block;"></i>
            <p style="color:var(--text-secondary);">Selecciona un dominio del panel izquierdo para gestionar su seguridad de email.</p>
        </div>
        @endif

    </div>

    <div wire:loading style="position:fixed;bottom:24px;right:24px;z-index:300;">
        <div class="glass" style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
        </div>
    </div>
</div>
