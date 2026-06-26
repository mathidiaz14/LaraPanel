<div>
<div style="max-width:680px;margin:0 auto;">

    {{-- Header --}}
    <div class="page-header" style="justify-content: flex-start; gap: 14px;">
        <a href="{{ route('ssl.index') }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-certificate" style="color:#f0a500;"></i> Emitir con Let's Encrypt
            </h1>
            <p class="page-subtitle">
                Certificado SSL gratuito y automático. Válido 90 días, con auto-renovación.
            </p>
        </div>
    </div>

    {{-- Success --}}
    @if($success)
    <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:12px;padding:28px;text-align:center;margin-bottom:20px;">
        <i class="fa-solid fa-circle-check" style="font-size:42px;color:var(--success);display:block;margin-bottom:14px;"></i>
        <div style="font-size:16px;font-weight:700;margin-bottom:8px;">¡SSL Activado!</div>
        <div style="font-size:13px;color:var(--text-secondary);margin-bottom:18px;">{{ $successMsg }}</div>
        <a href="{{ route('ssl.index') }}" class="btn btn-primary">
            <i class="fa-solid fa-lock"></i> Ver certificados
        </a>
    </div>
    @else

    {{-- Error --}}
    @if($errorMsg)
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i> {{ $errorMsg }}
    </div>
    @endif

    {{-- Loading overlay (Livewire handles this during AJAX request) --}}
    <div wire:loading wire:target="issue" style="width:100%;">
        <div class="glass lp-panel" style="text-align:center;margin-bottom:20px;">
            <i class="fa-solid fa-shield-halved fa-spin" style="font-size:40px;color:var(--success);display:block;margin-bottom:16px;"></i>
            <div style="font-size:15px;font-weight:600;margin-bottom:8px;">Contactando a Let's Encrypt...</div>
            <div style="font-size:13px;color:var(--text-secondary);">
                Verificando el dominio, emitiendo el certificado e instalando en Nginx.<br>
                <span style="color:var(--text-muted);">Esto puede tomar hasta 60 segundos.</span>
            </div>
            <div style="margin-top:18px;height:4px;background:var(--glass-border);border-radius:2px;overflow:hidden;">
                <div style="height:100%;background:linear-gradient(90deg,var(--success),#34d399);border-radius:2px;animation:progress-anim 2s ease-in-out infinite;"></div>
            </div>
        </div>
    </div>
    <style>@keyframes progress-anim{0%{width:5%}50%{width:70%}100%{width:95%}}</style>

    <div wire:loading.remove wire:target="issue">
        <div class="glass lp-panel">

            {{-- Domain selector --}}
            <div class="form-group">
                <label class="form-label">Dominio <span style="color:var(--danger)">*</span></label>
                <select wire:model.live="domainId" class="form-input">
                    <option value="">-- Selecciona un dominio --</option>
                    @foreach($domains as $domain)
                    <option value="{{ $domain->id }}" {{ $domain->ssl_enabled ? 'style=color:var(--success)' : '' }}>
                        {{ $domain->name }}
                        @if($domain->ssl_enabled) ✓ SSL activo @endif
                    </option>
                    @endforeach
                </select>
                @error('domainId') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            {{-- Options --}}
            <div style="display:grid;grid-template-columns:1fr;gap:12px;margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:8px;cursor:pointer;">
                    <input type="checkbox" wire:model="includeWww" style="accent-color:var(--accent);width:16px;height:16px;" {{ $isWildcard ? 'disabled' : '' }}>
                    <div>
                        <div style="font-size:13px;font-weight:600;{{ $isWildcard ? 'color:var(--text-muted);' : '' }}">Incluir www.{{ $domainId ? ($domains->firstWhere('id',$domainId)?->name ?? 'dominio.com') : 'dominio.com' }}</div>
                        <div style="font-size:11px;color:var(--text-muted);">Recomendado — cubre tanto dominio.com como www.dominio.com en el mismo cert.</div>
                    </div>
                </label>

                <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:8px;cursor:pointer;">
                    <input type="checkbox" wire:model.live="isWildcard" style="accent-color:var(--accent);width:16px;height:16px;">
                    <div>
                        <div style="font-size:13px;font-weight:600;">Certificado Wildcard (*.{{ $domainId ? ($domains->firstWhere('id',$domainId)?->name ?? 'dominio.com') : 'dominio.com' }})</div>
                        <div style="font-size:11px;color:var(--text-muted);">Cubre el dominio principal y todos sus subdominios. Requiere validación local por DNS (PowerDNS).</div>
                    </div>
                </label>
            </div>

            {{-- Extra SANs --}}
            <div class="form-group" style="{{ $isWildcard ? 'display:none;' : '' }}">
                <label class="form-label">Dominios adicionales (SAN) <span style="color:var(--text-muted);font-weight:400;">opcional</span></label>
                <div style="display:flex;gap:8px;">
                    <input wire:model="newSan" type="text" class="form-input" placeholder="api.tudominio.com"
                           wire:keydown.enter.prevent="addSan">
                    <button wire:click="addSan" type="button" class="btn btn-ghost" style="flex-shrink:0;">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                    Máximo 10 dominios adicionales. Todos deben apuntar a este servidor.
                </div>
                @if(count($extraSans) > 0)
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
                    @foreach($extraSans as $i => $san)
                    <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.25);border-radius:20px;font-size:12px;">
                        {{ $san }}
                        <button wire:click="removeSan({{ $i }})" style="background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0;font-size:12px;">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </span>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- What will happen --}}
            @if($domainId)
            <div style="background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.15);border-radius:10px;padding:14px;margin-bottom:20px;">
                <div style="font-size:12px;font-weight:600;color:var(--success);margin-bottom:8px;">
                    <i class="fa-solid fa-list-check"></i> Lo que ocurrirá
                </div>
                <ol style="font-size:12px;color:var(--text-secondary);padding-left:18px;line-height:2;">
                    @if($isWildcard)
                    <li>Se usará la validación por DNS (desafío DNS-01 en PowerDNS local) para demostrar la propiedad del dominio</li>
                    <li>Let's Encrypt emitirá un certificado Wildcard (*.{{ $domains->firstWhere('id',$domainId)?->name }}) válido por <strong>90 días</strong></li>
                    @else
                    <li>Se verificará que <strong>{{ $domains->firstWhere('id',$domainId)?->name }}</strong> apunta a este servidor</li>
                    <li>Let's Encrypt emitirá un certificado válido por <strong>90 días</strong></li>
                    @endif
                    <li>Se instalará en Nginx con HTTPS redirect automático (HTTP→HTTPS)</li>
                    <li>El certificado se <strong>renovará automáticamente</strong> antes de expirar</li>
                </ol>
            </div>
            @endif

            <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:8px;border-top:1px solid var(--glass-border);">
                <a href="{{ route('ssl.index') }}" class="btn btn-ghost">Cancelar</a>
                <button wire:click="issue" class="btn btn-primary" style="background:linear-gradient(135deg,#059669,#10b981);">
                    <i class="fa-solid fa-certificate"></i> Emitir certificado SSL
                </button>
            </div>
        </div>
    </div>
    @endif
    @endif

    {{-- Info footer --}}
    <div style="margin-top:14px;text-align:center;font-size:11px;color:var(--text-muted);">
        <i class="fa-solid fa-shield-halved" style="color:var(--success);margin-right:4px;"></i>
        Powered by Let's Encrypt ACME · Certificados renovados automáticamente
    </div>
</div>
</div>
