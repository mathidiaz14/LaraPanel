<div>
    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">SSL / TLS Manager</h1>
            <p style="color:var(--text-secondary);font-size:13px;">
                {{ $certificates->count() }} certificado(s) gestionados · Auto-renovación activa
            </p>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('ssl.issue') }}" class="btn btn-primary">
                <i class="fa-solid fa-certificate"></i> Let's Encrypt
            </a>
            <a href="{{ route('ssl.install') }}" class="btn btn-ghost">
                <i class="fa-solid fa-file-import"></i> Instalar Cert.
            </a>
        </div>
    </div>

    {{-- Alert --}}
    @if(session('success'))
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>
    @endif

    {{-- Domains without SSL banner --}}
    @if($domainsWithoutSsl->isNotEmpty())
    <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.25);border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
        <i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);font-size:18px;flex-shrink:0;"></i>
        <div style="flex:1;">
            <div style="font-size:13px;font-weight:600;color:var(--warning);">
                {{ $domainsWithoutSsl->count() }} dominio(s) sin SSL
            </div>
            <div style="font-size:12px;color:var(--text-secondary);">
                {{ $domainsWithoutSsl->pluck('name')->join(', ') }}
            </div>
        </div>
        <a href="{{ route('ssl.issue') }}" class="btn btn-ghost btn-sm" style="border-color:rgba(245,158,11,0.3);color:var(--warning);flex-shrink:0;">
            Activar SSL
        </a>
    </div>
    @endif

    {{-- Certificates list --}}
    @if($certificates->isEmpty())
    <div class="glass" style="padding:60px;text-align:center;">
        <div style="font-size:48px;opacity:0.25;margin-bottom:16px;"><i class="fa-solid fa-lock"></i></div>
        <p style="color:var(--text-secondary);margin-bottom:20px;">No hay certificados SSL instalados.<br>Activa HTTPS en tus dominios con un clic.</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <a href="{{ route('ssl.issue') }}" class="btn btn-primary">
                <i class="fa-brands fa-leanpub"></i> Emitir con Let's Encrypt
            </a>
            <a href="{{ route('ssl.install') }}" class="btn btn-ghost">
                <i class="fa-solid fa-file-import"></i> Instalar cert. propio
            </a>
        </div>
    </div>
    @else

    {{-- Cert cards grid --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px;">
        @foreach($certificates as $cert)
        @php
            $days = $cert->daysUntilExpiry();
            $isExpiringSoon = $cert->isExpiringSoon(30);
            $isExpired = $cert->isExpired();
            $cardBorder = $isExpired ? 'rgba(239,68,68,0.3)' : ($isExpiringSoon ? 'rgba(245,158,11,0.3)' : 'rgba(16,185,129,0.2)');
        @endphp
        <div class="glass" style="padding:20px;border-color:{{ $cardBorder }};position:relative;overflow:hidden;">
            {{-- Provider badge --}}
            <div style="position:absolute;top:16px;right:16px;">
                @if($cert->provider === 'letsencrypt')
                <span class="badge badge-success" style="font-size:10px;">
                    <i class="fa-solid fa-shield-halved"></i> Let's Encrypt
                </span>
                @elseif($cert->provider === 'custom')
                <span class="badge badge-accent" style="font-size:10px;">
                    <i class="fa-solid fa-building"></i> Custom
                </span>
                @else
                <span class="badge badge-muted" style="font-size:10px;">
                    <i class="fa-solid fa-certificate"></i> Self-signed
                </span>
                @endif
            </div>

            {{-- Domain --}}
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-right:90px;">
                <div style="width:38px;height:38px;border-radius:9px;background:{{ $isExpired ? 'rgba(239,68,68,0.12)' : ($isExpiringSoon ? 'rgba(245,158,11,0.12)' : 'rgba(16,185,129,0.12)') }};display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-{{ $isExpired ? 'lock-open' : 'lock' }}" style="color:{{ $isExpired ? 'var(--danger)' : ($isExpiringSoon ? 'var(--warning)' : 'var(--success)') }};font-size:16px;"></i>
                </div>
                <div>
                    <div style="font-weight:600;font-size:14px;">{{ $cert->domain?->name ?? 'Dominio eliminado' }}</div>
                    <div style="font-size:11px;color:var(--text-muted);">HTTPS habilitado</div>
                </div>
            </div>

            {{-- Details --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
                <div style="padding:10px;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:8px;">
                    <div style="font-size:10px;color:var(--text-muted);margin-bottom:3px;">ESTADO</div>
                    <div style="font-size:13px;font-weight:600;color:{{ $isExpired ? 'var(--danger)' : ($isExpiringSoon ? 'var(--warning)' : 'var(--success)') }};">
                        {{ $cert->statusLabel() }}
                    </div>
                </div>
                <div style="padding:10px;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:8px;">
                    <div style="font-size:10px;color:var(--text-muted);margin-bottom:3px;">EXPIRA</div>
                    <div style="font-size:13px;font-weight:600;color:{{ $isExpired ? 'var(--danger)' : ($isExpiringSoon ? 'var(--warning)' : 'var(--text-primary)') }};">
                        @if($days !== null)
                            @if($isExpired)
                                <i class="fa-solid fa-circle-exclamation"></i> Expirado
                            @elseif($days === 0)
                                Hoy
                            @elseif($days <= 7)
                                En {{ $days }}d
                            @else
                                {{ $cert->expires_at?->format('d/m/Y') }}
                            @endif
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>

            {{-- Expiry bar --}}
            @if($cert->issued_at && $cert->expires_at)
            @php
                $total    = $cert->issued_at->diffInDays($cert->expires_at);
                $elapsed  = $cert->issued_at->diffInDays(now());
                $pct      = $total > 0 ? min(100, round($elapsed / $total * 100)) : 100;
                $barClass = $isExpired ? 'danger' : ($isExpiringSoon ? 'warning' : 'success');
            @endphp
            <div class="progress-bar" style="margin-bottom:14px;">
                <div class="progress-fill {{ $barClass }}" style="width:{{ $pct }}%"></div>
            </div>
            @endif

            {{-- SANs --}}
            @if($cert->san_domains && count($cert->san_domains) > 1)
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:14px;">
                <i class="fa-solid fa-list"></i> SANs: {{ implode(', ', $cert->san_domains) }}
            </div>
            @endif

            {{-- Actions --}}
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                @if($cert->provider === 'letsencrypt' && !$isExpired)
                <a href="{{ route('ssl.issue') }}?domain={{ $cert->domain_id }}" class="btn btn-ghost btn-sm">
                    <i class="fa-solid fa-rotate"></i> Renovar
                </a>
                @endif
                <a href="{{ route('ssl.install') }}?domain={{ $cert->domain_id }}" class="btn btn-ghost btn-sm">
                    <i class="fa-solid fa-arrow-up-from-bracket"></i> Reemplazar
                </a>
                <button wire:click="confirmRevoke({{ $cert->id }})"
                        class="btn btn-danger btn-sm" style="margin-left:auto;">
                    <i class="fa-solid fa-trash"></i> Revocar
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Auto-renewal info box --}}
    <div style="margin-top:24px;" class="glass" style="padding:18px;">
        <div class="glass" style="padding:18px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <i class="fa-solid fa-rotate" style="color:var(--accent-light);font-size:20px;"></i>
                <div>
                    <div style="font-size:13px;font-weight:600;">Auto-renovación activa</div>
                    <div style="font-size:12px;color:var(--text-secondary);">
                        Los certificados Let's Encrypt se renuevan automáticamente 30 días antes de expirar
                        vía el scheduler de Laravel. No necesitas hacer nada.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Revoke Modal --}}
    @if($revokingId)
    <div style="position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;">
        <div class="glass-elevated" style="max-width:420px;width:100%;padding:32px;margin:16px;text-align:center;">
            <div style="width:52px;height:52px;border-radius:50%;background:rgba(239,68,68,0.15);border:2px solid rgba(239,68,68,0.3);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <i class="fa-solid fa-lock-open" style="color:var(--danger);font-size:20px;"></i>
            </div>
            <h2 style="font-size:17px;font-weight:700;margin-bottom:8px;">Revocar certificado SSL</h2>
            <p style="color:var(--text-secondary);font-size:13px;margin-bottom:22px;">
                El dominio pasará a HTTP. Si es Let's Encrypt, el certificado se revocará en los servidores de ACME.
            </p>
            <div style="display:flex;gap:10px;">
                <button wire:click="$set('revokingId', null)" class="btn btn-ghost" style="flex:1;justify-content:center;">Cancelar</button>
                <button wire:click="revokeCertificate" class="btn btn-danger" style="flex:1;justify-content:center;">
                    <i class="fa-solid fa-lock-open"></i> Revocar
                </button>
            </div>
        </div>
    </div>
    @endif

    <div wire:loading.delay style="position:fixed;bottom:24px;right:24px;z-index:300;">
        <div class="glass" style="padding:10px 16px;font-size:13px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
        </div>
    </div>
</div>
