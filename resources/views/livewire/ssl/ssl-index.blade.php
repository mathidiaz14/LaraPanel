<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">SSL / TLS Manager</h1>
            <p class="page-subtitle">
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
    <div class="table-responsive">
        <table class="lp-table">
            <thead>
                <tr>
                    <th>Dominio</th>
                    <th>Proveedor</th>
                    <th>Estado</th>
                    <th>Vencimiento</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            @foreach($groupedCerts as $groupName => $groupData)
            @php 
                $cert = $groupData['main'];
                $subcerts = $groupData['subdomains'];
                $hasSubs = count($subcerts) > 0;
            @endphp
            <tbody x-data="{ expanded: false }">
                <tr wire:key="cert-{{ $cert->id }}">
                    @php
                        $days = $cert->daysUntilExpiry();
                        $isExpiringSoon = $cert->isExpiringSoon(30);
                        $isExpired = $cert->isExpired();
                    @endphp
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if($hasSubs)
                            <button @click="expanded = !expanded" class="btn btn-ghost btn-sm" style="padding:4px;border-radius:4px;width:24px;height:24px;margin-right:-4px;">
                                <i class="fa-solid fa-chevron-right" style="font-size:11px;transition:transform 0.2s;" :style="expanded ? 'transform:rotate(90deg)' : ''"></i>
                            </button>
                            @endif
                            <div style="width:34px;height:34px;border-radius:8px;background:{{ $isExpired ? 'rgba(239,68,68,0.12)' : ($isExpiringSoon ? 'rgba(245,158,11,0.12)' : 'rgba(16,185,129,0.12)') }};border:1px solid {{ $isExpired ? 'rgba(239,68,68,0.2)' : ($isExpiringSoon ? 'rgba(245,158,11,0.2)' : 'rgba(16,185,129,0.2)') }};display:flex;align-items:center;justify-content:center;{{ !$hasSubs ? 'margin-left:24px;' : '' }}">
                                <i class="fa-solid fa-{{ $isExpired ? 'lock-open' : 'lock' }}" style="color:{{ $isExpired ? 'var(--danger)' : ($isExpiringSoon ? 'var(--warning)' : 'var(--success)') }};font-size:14px;"></i>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:14px;">{{ $cert->domain?->name ?? 'Dominio eliminado' }}</div>
                                @if($cert->san_domains && count($cert->san_domains) > 1)
                                <div style="font-size:11px;color:var(--text-muted);">{{ count($cert->san_domains) }} SANs</div>
                                @else
                                <div style="font-size:11px;color:var(--text-muted);">HTTPS habilitado</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>
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
                    </td>
                    <td>
                        <span style="font-size:12px;font-weight:600;color:{{ $isExpired ? 'var(--danger)' : ($isExpiringSoon ? 'var(--warning)' : 'var(--success)') }};">
                            {{ $cert->statusLabel() }}
                        </span>
                    </td>
                    <td>
                        <span style="font-size:12px;font-weight:600;color:{{ $isExpired ? 'var(--danger)' : ($isExpiringSoon ? 'var(--warning)' : 'var(--text-primary)') }};">
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
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <div class="lp-row-actions">
                            @if($cert->provider === 'letsencrypt' && !$isExpired)
                            <a href="{{ route('ssl.issue') }}?domain={{ $cert->domain_id }}" class="btn btn-ghost btn-sm" title="Renovar">
                                <i class="fa-solid fa-rotate"></i>
                            </a>
                            @endif
                            <a href="{{ route('ssl.install') }}?domain={{ $cert->domain_id }}" class="btn btn-ghost btn-sm" title="Reemplazar">
                                <i class="fa-solid fa-arrow-up-from-bracket"></i>
                            </a>
                            <button wire:click="confirmRevoke({{ $cert->id }})" class="btn btn-danger btn-sm" title="Revocar">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>

                @foreach($subcerts as $subcert)
                <tr wire:key="cert-{{ $subcert->id }}" x-show="expanded" style="background:rgba(255,255,255,0.015);display:none;" x-transition>
                    @php
                        $sDays = $subcert->daysUntilExpiry();
                        $sIsExpiringSoon = $subcert->isExpiringSoon(30);
                        $sIsExpired = $subcert->isExpired();
                    @endphp
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;padding-left:34px;">
                            <div style="width:28px;height:28px;border-radius:8px;background:{{ $sIsExpired ? 'rgba(239,68,68,0.08)' : ($sIsExpiringSoon ? 'rgba(245,158,11,0.08)' : 'rgba(16,185,129,0.08)') }};border:1px solid {{ $sIsExpired ? 'rgba(239,68,68,0.15)' : ($sIsExpiringSoon ? 'rgba(245,158,11,0.15)' : 'rgba(16,185,129,0.15)') }};display:flex;align-items:center;justify-content:center;">
                                <i class="fa-solid fa-{{ $sIsExpired ? 'lock-open' : 'lock' }}" style="color:{{ $sIsExpired ? 'var(--danger)' : ($sIsExpiringSoon ? 'var(--warning)' : 'var(--success)') }};font-size:12px;"></i>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13px;color:var(--text-secondary);">{{ $subcert->domain?->name ?? 'Dominio eliminado' }}</div>
                                <div style="font-size:11px;color:var(--text-muted);">HTTPS habilitado</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($subcert->provider === 'letsencrypt')
                            <span class="badge badge-success" style="font-size:10px;">
                                <i class="fa-solid fa-shield-halved"></i> Let's Encrypt
                            </span>
                        @elseif($subcert->provider === 'custom')
                            <span class="badge badge-accent" style="font-size:10px;">
                                <i class="fa-solid fa-building"></i> Custom
                            </span>
                        @else
                            <span class="badge badge-muted" style="font-size:10px;">
                                <i class="fa-solid fa-certificate"></i> Self-signed
                            </span>
                        @endif
                    </td>
                    <td>
                        <span style="font-size:12px;font-weight:600;color:{{ $sIsExpired ? 'var(--danger)' : ($sIsExpiringSoon ? 'var(--warning)' : 'var(--success)') }};">
                            {{ $subcert->statusLabel() }}
                        </span>
                    </td>
                    <td>
                        <span style="font-size:12px;font-weight:600;color:{{ $sIsExpired ? 'var(--danger)' : ($sIsExpiringSoon ? 'var(--warning)' : 'var(--text-primary)') }};">
                            @if($sDays !== null)
                                @if($sIsExpired)
                                    <i class="fa-solid fa-circle-exclamation"></i> Expirado
                                @elseif($sDays === 0)
                                    Hoy
                                @elseif($sDays <= 7)
                                    En {{ $sDays }}d
                                @else
                                    {{ $subcert->expires_at?->format('d/m/Y') }}
                                @endif
                            @else
                                —
                            @endif
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <div class="lp-row-actions">
                            @if($subcert->provider === 'letsencrypt' && !$sIsExpired)
                            <a href="{{ route('ssl.issue') }}?domain={{ $subcert->domain_id }}" class="btn btn-ghost btn-sm" title="Renovar">
                                <i class="fa-solid fa-rotate"></i>
                            </a>
                            @endif
                            <a href="{{ route('ssl.install') }}?domain={{ $subcert->domain_id }}" class="btn btn-ghost btn-sm" title="Reemplazar">
                                <i class="fa-solid fa-arrow-up-from-bracket"></i>
                            </a>
                            <button wire:click="confirmRevoke({{ $subcert->id }})" class="btn btn-danger btn-sm" title="Revocar">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
            @endforeach
        </table>
    </div>
    @endif

    {{-- Auto-renewal info box --}}
    <div class="glass lp-panel" style="margin-top:24px;">
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

    {{-- Revoke Modal --}}
    @if($revokingId)
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated" style="max-width:420px;text-align:center;">
            <div class="lp-modal-body">
                <div style="width:52px;height:52px;border-radius:50%;background:rgba(239,68,68,0.15);border:2px solid rgba(239,68,68,0.3);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                    <i class="fa-solid fa-lock-open" style="color:var(--danger);font-size:20px;"></i>
                </div>
                <h2 style="font-size:17px;font-weight:700;margin-bottom:8px;">Revocar certificado SSL</h2>
                <p style="color:var(--text-secondary);font-size:13px;margin-bottom:22px;">
                    El dominio pasará a HTTP. Si es Let's Encrypt, el certificado se revocará en los servidores de ACME.
                </p>
            </div>
            <div class="lp-modal-footer">
                <button wire:click="$set('revokingId', null)" class="btn btn-ghost" style="flex:1;justify-content:center;">Cancelar</button>
                <button wire:click="revokeCertificate" class="btn btn-danger" style="flex:1;justify-content:center;">
                    <i class="fa-solid fa-lock-open"></i> Revocar
                </button>
            </div>
        </div>
    </div>
    @endif

    <div wire:loading.delay class="lp-loading-toast">
        <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
    </div>
</div>
