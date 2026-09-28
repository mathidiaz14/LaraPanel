<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Monitor de Certificados SSL</h1>
            <p class="page-subtitle">
                Resumen del estado de HTTPS en todos los dominios activos del servidor.
            </p>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('ssl.index') }}" class="btn btn-ghost">
                <i class="fa-solid fa-lock"></i> Gestión SSL
            </a>
            <a href="{{ route('ssl.issue') }}" class="btn btn-primary">
                <i class="fa-solid fa-certificate"></i> Let's Encrypt
            </a>
        </div>
    </div>

    {{-- Summary cards --}}
    @php
        $expiredCount = $groups['expired']->count();
        $soonCount = $groups['expiring_soon']->count();
        $laterCount = $groups['expiring_later']->count();
        $noSslCount = $groups['no_ssl']->count();
    @endphp

    <div class="stats-row">
        <div class="glass" style="padding:16px;text-align:center;">
            <div style="font-size:24px;font-weight:700;color:var(--danger);">{{ $expiredCount }}</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">Expirados</div>
        </div>
        <div class="glass" style="padding:16px;text-align:center;">
            <div style="font-size:24px;font-weight:700;color:{{ $soonCount > 0 ? 'var(--warning)' : 'var(--text-muted)' }};">{{ $soonCount }}</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">Expiran en ≤ {{ $scanDays }} días</div>
        </div>
        <div class="glass" style="padding:16px;text-align:center;">
            <div style="font-size:24px;font-weight:700;color:var(--success);">{{ $laterCount }}</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">Certificados Saludables</div>
        </div>
        <div class="glass" style="padding:16px;text-align:center;">
            <div style="font-size:24px;font-weight:700;color:var(--text-muted);">{{ $noSslCount }}</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">Dominios Sin SSL</div>
        </div>
    </div>

    {{-- Alert banner --}}
    @if($expiredCount > 0 || $soonCount > 0)
    <div style="background:color-mix(in srgb, var(--danger) 8%, transparent);border:1px solid color-mix(in srgb, var(--danger) 25%, transparent);border-radius:var(--radius-sm);padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
        <i class="fa-solid fa-triangle-exclamation" style="color:var(--danger);font-size:18px;flex-shrink:0;"></i>
        <div style="flex:1;">
            <div style="font-size:13px;font-weight:600;color:var(--danger);">
                Se requiere atención: {{ $expiredCount + $soonCount }} certificado(s) con riesgo
            </div>
            <div style="font-size:12px;color:var(--text-secondary);">
                Renueva los certificados a continuación para evitar interrupciones de HTTPS.
            </div>
        </div>
    </div>
    @endif

    {{-- Certificates table --}}
    @php
        $rows = $groups['expired']
            ->concat($groups['expiring_soon'])
            ->concat($groups['expiring_later'])
            ->concat($groups['no_ssl'])
            ->map(fn ($d) => [
                'domain'  => $d,
                'days'    => $d->sslExpiresInDays(),
            ]);
    @endphp

    <div class="glass lp-panel">
        <h2 class="panel-title">
            <i class="fa-solid fa-list-check" style="color:var(--accent-light);"></i>
            Estado por Dominio
        </h2>

        @if($rows->isEmpty())
        <div style="text-align:center;padding:clamp(24px,8vw,60px) 16px;color:var(--text-secondary);">
            <i class="fa-solid fa-shield-halved" style="font-size:40px;opacity:0.25;margin-bottom:14px;display:block;"></i>
            No hay dominios activos registrados en el servidor.
        </div>
        @else
        <div style="overflow-x:auto;">
            <table class="lp-table">
                <thead>
                    <tr>
                        <th>Dominio</th>
                        <th>Proveedor</th>
                        <th>Estado</th>
                        <th>Días Restantes</th>
                        <th>Vencimiento</th>
                        <th style="text-align:right;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    @php
                        $d = $row['domain'];
                        $days = $row['days'];
                        $cert = $d->sslCertificate;

                        if ($days === null) {
                            $severity = 'none';
                            $badge = 'badge-muted';
                            $label = 'Sin SSL';
                            $icon = 'circle-question';
                            $color = 'var(--text-muted)';
                        } elseif ($days < 0) {
                            $severity = 'expired';
                            $badge = 'badge-danger';
                            $label = 'Expirado';
                            $icon = 'lock-open';
                            $color = 'var(--danger)';
                        } elseif ($days === 0) {
                            $severity = 'now';
                            $badge = 'badge-danger';
                            $label = 'Hoy';
                            $icon = 'clock';
                            $color = 'var(--danger)';
                        } elseif ($days <= 7) {
                            $severity = 'soon';
                            $badge = 'badge-warning';
                            $label = 'Crítico';
                            $icon = 'triangle-exclamation';
                            $color = 'var(--warning)';
                        } elseif ($days <= $scanDays) {
                            $severity = 'soon';
                            $badge = 'badge-warning';
                            $label = 'Próximo';
                            $icon = 'triangle-exclamation';
                            $color = 'var(--warning)';
                        } else {
                            $severity = 'ok';
                            $badge = 'badge-success';
                            $label = 'OK';
                            $icon = 'shield-halved';
                            $color = 'var(--success)';
                        }

                        $renew = $days !== null && $days <= $scanDays;
                    @endphp
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;border-radius:var(--radius-sm);background:color-mix(in srgb, {{ $color }} 12%, transparent);border:1px solid color-mix(in srgb, {{ $color }} 20%, transparent);display:flex;align-items:center;justify-content:center;">
                                    <i class="fa-solid fa-{{ $icon }}" style="color:{{ $color }};font-size:14px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight:600;font-size:14px;">{{ $d->name }}</div>
                                    <div style="font-size:11px;color:var(--text-muted);">{{ $d->user?->email ?? 'Propietario' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($cert && $cert->provider)
                                @if($cert->provider === 'letsencrypt')
                                    <span class="badge badge-success" style="font-size:10px;"><i class="fa-solid fa-shield-halved"></i> Let's Encrypt</span>
                                @elseif($cert->provider === 'custom')
                                    <span class="badge badge-accent" style="font-size:10px;"><i class="fa-solid fa-building"></i> Custom</span>
                                @else
                                    <span class="badge badge-muted" style="font-size:10px;"><i class="fa-solid fa-certificate"></i> {{ $cert->providerLabel() }}</span>
                                @endif
                            @else
                                <span class="badge badge-muted" style="font-size:10px;">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $badge }}" style="font-size:11px;">{{ $label }}</span>
                        </td>
                        <td>
                            @if($days === null)
                                <span style="font-size:12px;color:var(--text-muted);">—</span>
                            @elseif($days < 0)
                                <span style="font-size:12px;font-weight:700;color:var(--danger);">Hace {{ abs($days) }}d</span>
                            @elseif($days === 0)
                                <span style="font-size:12px;font-weight:700;color:var(--danger);">Hoy</span>
                            @else
                                <span style="font-size:12px;font-weight:700;color:{{ $renew ? 'var(--warning)' : 'var(--success)' }};">{{ $days }}d</span>
                            @endif
                        </td>
                        <td>
                            <span style="font-size:12px;color:var(--text-secondary);">
                                {{ $d->ssl_expires_at?->format('d/m/Y') ?? '—' }}
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <div class="lp-row-actions">
                                @if($renew)
                                    <a href="{{ route('ssl.issue') }}?domain={{ $d->id }}" class="btn btn-ghost btn-sm" title="Renovar certificado">
                                        <i class="fa-solid fa-rotate" style="color:var(--warning);"></i>
                                    </a>
                                @elseif($days === null)
                                    <a href="{{ route('ssl.issue', ['domain' => $d->id]) }}" class="btn btn-ghost btn-sm" title="Activar SSL">
                                        <i class="fa-solid fa-lock" style="color:var(--accent-light);"></i>
                                    </a>
                                @else
                                    <a href="{{ route('ssl.install') }}?domain={{ $d->id }}" class="btn btn-ghost btn-sm" title="Reemplazar/instalar certificado">
                                        <i class="fa-solid fa-file-import" style="color:var(--text-muted);"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>