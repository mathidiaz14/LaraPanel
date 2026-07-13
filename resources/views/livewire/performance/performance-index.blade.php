<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-shield-halved" style="color:var(--accent-light)"></i>
                Performance & WAF
            </h1>
            <p class="page-subtitle">Optimización, seguridad y CDN por dominio — Fase 10</p>
        </div>
        {{-- Domain selector --}}
        <select wire:model.live="domainId" class="form-input" style="width:220px;margin-bottom:0;">
            <option value="">Seleccionar dominio...</option>
            @foreach($domains as $d)
            <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Alerts --}}
    @if($successMessage)
    <div class="alert alert-success" x-data x-init="setTimeout(()=>$el.remove(),4000)">
        <i class="fa-solid fa-circle-check"></i> {{ $successMessage }}
    </div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}
    </div>
    @endif

    @if(!$domainId)
    <div class="empty-state">
        <i class="fa-solid fa-shield-halved empty-state-icon"></i>
        <p class="empty-state-text">Selecciona un dominio para gestionar sus opciones de performance y seguridad.</p>
    </div>
    @else

    {{-- Tabs --}}
    <div class="lp-tabs" style="margin-bottom:20px;">
        @foreach([
            ['attack',    'fa-bolt',          'Under Attack'],
            ['cache',     'fa-gauge-high',     'Microcaché'],
            ['geowaf',    'fa-earth-americas', 'Geo-WAF'],
            ['proxy',     'fa-cloud',          'Orange Cloud'],
            ['analytics', 'fa-chart-bar',      'Analytics'],
            ['pagerules', 'fa-sliders',        'Page Rules'],
        ] as [$tab, $icon, $label])
        <button wire:click="$set('activeTab','{{ $tab }}')"
                class="lp-tab {{ $activeTab === $tab ? 'active' : '' }}">
            <i class="fa-solid {{ $icon }}"></i> {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- ① Under Attack Mode ─────────────────────────────────── --}}
    @if($activeTab === 'attack')
    <div class="lp-two-col">
        <div class="glass lp-panel">
            <h2 class="panel-title"><i class="fa-solid fa-bolt" style="color:var(--warning);margin-right:8px;"></i>Under Attack Mode</h2>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px;">
                Activa limitadores de tráfico en el vhost de Nginx para mitigar ataques DDoS y bots agresivos.
            </p>
            <div class="form-group">
                <label class="form-label" style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                    <input type="checkbox" wire:model="underAttack" style="width:18px;height:18px;accent-color:var(--warning);">
                    <span>Activar Under Attack Mode</span>
                    @if($underAttack)
                    <span class="badge badge-warning">ACTIVO</span>
                    @endif
                </label>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:16px;">
                <div class="form-group">
                    <label class="form-label">Rate (req/s)</label>
                    <input type="number" wire:model="attackRate" class="form-input" min="1" max="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Burst</label>
                    <input type="number" wire:model="attackBurst" class="form-input" min="1" max="500">
                </div>
                <div class="form-group">
                    <label class="form-label">Max Conexiones</label>
                    <input type="number" wire:model="attackConn" class="form-input" min="1" max="100">
                </div>
            </div>
            <button wire:click="saveUnderAttack" class="btn {{ $underAttack ? 'btn-danger' : 'btn-primary' }}" style="margin-top:8px;" wire:loading.attr="disabled">
                <span wire:loading.remove><i class="fa-solid fa-save"></i> Guardar y Recargar Nginx</span>
                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Aplicando...</span>
            </button>
        </div>
        <div class="glass lp-panel">
            <h2 class="panel-title" style="margin-bottom:12px;">¿Cuándo usarlo?</h2>
            <ul style="font-size:13px;color:var(--text-secondary);line-height:2;padding-left:16px;">
                <li>Ataques DDoS Layer 7 (HTTP flood)</li>
                <li>Bots de scraping agresivos</li>
                <li>Picos anómalos de tráfico</li>
                <li>Intentos de brute-force en login</li>
            </ul>
            <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:8px;padding:12px;margin-top:16px;font-size:12px;color:var(--text-secondary);">
                <i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);margin-right:6px;"></i>
                Los usuarios legítimos con muchas pestañas abiertas pueden recibir errores 503 temporales.
            </div>
        </div>
    </div>
    @endif

    {{-- ② FastCGI Microcaching ──────────────────────────────── --}}
    @if($activeTab === 'cache')
    <div class="lp-two-col">
        <div class="glass lp-panel">
            <h2 class="panel-title"><i class="fa-solid fa-gauge-high" style="color:var(--success);margin-right:8px;"></i>FastCGI Microcaching</h2>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px;">
                Nginx cachea la salida PHP en disco. Las páginas dinámicas se sirven como si fueran estáticas.
            </p>
            <div class="form-group">
                <label class="form-label" style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                    <input type="checkbox" wire:model="microcacheEnabled" style="width:18px;height:18px;accent-color:var(--success);">
                    <span>Activar Microcaché FastCGI</span>
                    @if($microcacheEnabled) <span class="badge badge-success">ACTIVO</span> @endif
                </label>
            </div>
            <div class="form-group" style="margin-top:16px;">
                <label class="form-label">TTL del caché (segundos)</label>
                <input type="number" wire:model="microcacheTtl" class="form-input" min="5" max="3600">
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Un valor de 60s es recomendado para sitios dinámicos como WordPress.</div>
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button wire:click="saveMicrocache" class="btn btn-primary" wire:loading.attr="disabled">
                    <i class="fa-solid fa-save"></i> Guardar
                </button>
                <button wire:click="purgeMicrocache" class="btn btn-ghost btn-sm" style="color:var(--warning);">
                    <i class="fa-solid fa-trash-can"></i> Purgar caché
                </button>
            </div>
            @if($microcachePurgedAt)
            <div style="font-size:11px;color:var(--text-muted);margin-top:8px;">Último purge: {{ $microcachePurgedAt }}</div>
            @endif
        </div>
        <div class="glass lp-panel">
            <h2 class="panel-title" style="margin-bottom:12px;">Header de diagnóstico</h2>
            <p style="font-size:13px;color:var(--text-secondary);">Cuando el caché está activo, todas las respuestas incluyen el header:</p>
            <code style="display:block;margin:12px 0;padding:10px 14px;background:rgba(0,0,0,0.3);border-radius:6px;font-size:12px;color:var(--accent-light);">X-Cache-Status: HIT | MISS | BYPASS</code>
            <p style="font-size:12px;color:var(--text-muted);">Usa el botón "Purgar" durante el desarrollo para forzar contenido fresco sin desactivar el caché.</p>
        </div>
    </div>
    @endif

    {{-- ③ Geo-WAF ───────────────────────────────────────────── --}}
    @if($activeTab === 'geowaf')
    <div class="lp-two-col">
        <div class="glass lp-panel">
            <h2 class="panel-title"><i class="fa-solid fa-earth-americas" style="color:var(--info);margin-right:8px;"></i>Geo-WAF (MaxMind)</h2>

            @if(!$geoModuleInstalled)
            <div class="alert alert-danger" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                Módulo <code>ngx_http_geoip2_module</code> no instalado. Ejecuta <code>apt install libnginx-mod-http-geoip2</code>.
            </div>
            @elseif(!$geoDbAvailable)
            <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;">
                <i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);margin-right:6px;"></i>
                Base de datos GeoLite2 no encontrada. Configura <code>MAXMIND_LICENSE_KEY</code> en .env y haz click en "Actualizar DB".
            </div>
            @endif

            <div class="form-group">
                <label class="form-label" style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                    <input type="checkbox" wire:model="geoWafEnabled" style="width:18px;height:18px;accent-color:var(--info);">
                    <span>Activar Geo-WAF</span>
                    @if($geoWafEnabled) <span class="badge badge-accent">ACTIVO</span> @endif
                </label>
            </div>
            <div class="form-group" style="margin-top:12px;">
                <label class="form-label">Modo</label>
                <select wire:model="geoWafMode" class="form-input">
                    <option value="block">Bloquear los países listados</option>
                    <option value="allow">Permitir SOLO los países listados</option>
                </select>
            </div>
            <div class="form-group" style="margin-top:12px;">
                <label class="form-label">Agregar país (código ISO)</label>
                <div style="display:flex;gap:8px;">
                    <input type="text" wire:model="geoWafCountryAdd" class="form-input" placeholder="ej. RU" maxlength="2" style="text-transform:uppercase;width:80px;">
                    <button wire:click="addGeoCountry" class="btn btn-ghost btn-sm"><i class="fa-solid fa-plus"></i></button>
                </div>
            </div>
            @if(!empty($geoWafCountries))
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;margin-bottom:12px;">
                @foreach($geoWafCountries as $code)
                <span style="background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.3);border-radius:6px;padding:3px 10px;font-size:12px;font-family:monospace;display:flex;align-items:center;gap:6px;">
                    {{ $code }}
                    <button wire:click="removeGeoCountry('{{ $code }}')" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:11px;padding:0;">✕</button>
                </span>
                @endforeach
            </div>
            @endif
            <div style="display:flex;gap:8px;margin-top:8px;">
                <button wire:click="saveGeoWaf" class="btn btn-primary btn-sm"><i class="fa-solid fa-save"></i> Guardar WAF</button>
                <button wire:click="updateGeoWafDatabase" class="btn btn-ghost btn-sm" style="color:var(--info);"><i class="fa-solid fa-cloud-arrow-down"></i> Actualizar DB</button>
            </div>
        </div>
        <div class="glass lp-panel">
            <h2 class="panel-title" style="margin-bottom:12px;">Países de alto riesgo comunes</h2>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                @foreach([['CN','China'],['RU','Russia'],['KP','Corea Norte'],['IR','Iran'],['BY','Belarus'],['CU','Cuba']] as [$c,$n])
                <button wire:click="$set('geoWafCountryAdd','{{ $c }}')" class="btn btn-ghost btn-sm" style="font-size:11px;">
                    {{ $c }} – {{ $n }}
                </button>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ④ Orange Cloud ───────────────────────────────────────── --}}
    @if($activeTab === 'proxy')
    <div class="lp-two-col">
        <div class="glass lp-panel">
            <h2 class="panel-title">
                <i class="fa-solid fa-cloud" style="color:#f6821f;margin-right:8px;"></i>
                Orange Cloud — Reverse Proxy
            </h2>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px;">
                Convierte Nginx en proxy frontal. El tráfico del dominio se redirige a un backend oculto.
            </p>
            <div class="form-group">
                <label class="form-label" style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                    <input type="checkbox" wire:model="orangeCloud" style="width:18px;height:18px;accent-color:#f6821f;">
                    <span>Activar Orange Cloud</span>
                    @if($orangeCloud) <span class="badge" style="background:rgba(246,130,31,0.2);color:#f6821f;">ACTIVO</span> @endif
                </label>
            </div>
            <div class="form-group" style="margin-top:16px;">
                <label class="form-label">Backend destino (URL o IP:Puerto)</label>
                <input type="text" wire:model="proxyTarget" class="form-input" placeholder="http://127.0.0.1:3000 o https://backend.example.com">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                <div class="form-group">
                    <label class="form-label">Timeout (s)</label>
                    <input type="number" wire:model="proxyTimeout" class="form-input" min="5" max="300">
                </div>
                <div class="form-group" style="display:flex;flex-direction:column;justify-content:flex-end;">
                    <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" wire:model="proxyWebsocket"> WebSocket Support
                    </label>
                    <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:8px;">
                        <input type="checkbox" wire:model="proxySslVerify"> Verificar SSL backend
                    </label>
                </div>
            </div>
            <button wire:click="saveProxyConfig" class="btn btn-primary" style="margin-top:16px;" wire:loading.attr="disabled">
                <i class="fa-solid fa-save"></i> Guardar Proxy
            </button>
        </div>
        <div class="glass lp-panel">
            <h2 class="panel-title" style="margin-bottom:12px;">Casos de uso</h2>
            <ul style="font-size:13px;color:var(--text-secondary);line-height:2;padding-left:16px;">
                <li>Exponer contenedores Docker bajo un dominio</li>
                <li>Esconder la IP real del backend</li>
                <li>Node.js / Python apps detrás de Nginx</li>
                <li>Balanceo simple entre puertos</li>
            </ul>
        </div>
    </div>
    @endif

    {{-- ⑤ GoAccess Analytics ────────────────────────────────── --}}
    @if($activeTab === 'analytics')
    <div class="lp-two-col">
        <div class="glass lp-panel">
            <h2 class="panel-title"><i class="fa-solid fa-chart-bar" style="color:var(--success);margin-right:8px;"></i>GoAccess Analytics</h2>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px;">
                Estadísticas de tráfico generadas desde los logs de Nginx. Sin cookies, sin Google Analytics.
            </p>
            <button wire:click="generateGoAccessReport" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove><i class="fa-solid fa-rotate"></i> Generar / Actualizar Reporte</span>
                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Generando...</span>
            </button>
            @if($goAccessGeneratedAt)
            <div style="margin-top:12px;font-size:12px;color:var(--text-muted);">
                <i class="fa-solid fa-clock"></i> Último reporte: {{ $goAccessGeneratedAt }}
            </div>
            @endif
        </div>
        <div class="glass lp-panel">
            @if($goAccessReportPath && file_exists($goAccessReportPath))
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">
                <i class="fa-solid fa-file-code"></i> {{ $goAccessReportPath }}
            </div>
            <div style="border-radius:8px;overflow:hidden;border:1px solid var(--glass-border);">
                {!! file_get_contents($goAccessReportPath) !!}
            </div>
            @else
            <div class="empty-state">
                <i class="fa-solid fa-chart-bar empty-state-icon"></i>
                <p class="empty-state-text">Genera el primer reporte haciendo click en el botón.</p>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ⑥ Page Rules ─────────────────────────────────────────── --}}
    @if($activeTab === 'pagerules')
    <div style="display:flex;flex-direction:column;gap:20px;">

        {{-- HSTS --}}
        <div class="glass lp-panel">
            <h2 class="panel-title"><i class="fa-solid fa-lock" style="color:var(--success);margin-right:8px;"></i>HSTS — HTTP Strict Transport Security</h2>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;margin-top:16px;align-items:end;">
                <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;align-self:center;">
                    <input type="checkbox" wire:model="hstsEnabled"> Activar HSTS
                </label>
                <div class="form-group">
                    <label class="form-label">max-age (s)</label>
                    <input type="number" wire:model="hstsMaxAge" class="form-input" min="0">
                </div>
                <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;align-self:center;">
                    <input type="checkbox" wire:model="hstsIncludeSubdomains"> includeSubDomains
                </label>
                <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;align-self:center;">
                    <input type="checkbox" wire:model="hstsPreload"> preload
                </label>
            </div>
            @if($hstsEnabled)
            <code style="display:block;margin-top:10px;padding:8px 12px;background:rgba(0,0,0,0.3);border-radius:6px;font-size:11px;color:var(--accent-light);">
                Strict-Transport-Security: max-age={{ $hstsMaxAge }}{{ $hstsIncludeSubdomains ? '; includeSubDomains' : '' }}{{ $hstsPreload ? '; preload' : '' }}
            </code>
            @endif
        </div>

        {{-- Custom Headers --}}
        <div class="glass lp-panel">
            <h2 class="panel-title" style="margin-bottom:16px;"><i class="fa-solid fa-heading" style="color:var(--accent-light);margin-right:8px;"></i>Headers Personalizados</h2>
            <div style="display:flex;gap:8px;margin-bottom:12px;">
                <input type="text" wire:model="newHeaderName" class="form-input" placeholder="Nombre (ej. X-Custom)" style="flex:1;">
                <input type="text" wire:model="newHeaderValue" class="form-input" placeholder="Valor" style="flex:2;">
                <button wire:click="addCustomHeader" class="btn btn-ghost btn-sm"><i class="fa-solid fa-plus"></i></button>
            </div>
            @if(!empty($customHeaders))
            <div class="glass" style="overflow:hidden;">
                <table class="lp-table">
                    <thead><tr><th>Header</th><th>Valor</th><th style="text-align:right">Acción</th></tr></thead>
                    <tbody>
                        @foreach($customHeaders as $i => $h)
                        <tr wire:key="header-{{ $i }}">
                            <td><code style="color:var(--accent-light);">{{ $h['name'] }}</code></td>
                            <td style="font-size:12px;color:var(--text-secondary);">{{ $h['value'] }}</td>
                            <td style="text-align:right;"><button wire:click="removeCustomHeader({{ $i }})" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- Redirects --}}
        <div class="glass lp-panel">
            <h2 class="panel-title" style="margin-bottom:16px;"><i class="fa-solid fa-right-long" style="color:var(--warning);margin-right:8px;"></i>Redirecciones</h2>
            <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
                <input type="text" wire:model="newRedirectFrom" class="form-input" placeholder="Desde (ej. /blog/)" style="flex:2;min-width:140px;">
                <input type="text" wire:model="newRedirectTo"   class="form-input" placeholder="Hacia (URL destino)" style="flex:3;min-width:180px;">
                <select wire:model="newRedirectCode" class="form-input" style="width:80px;">
                    <option value="301">301</option>
                    <option value="302">302</option>
                </select>
                <button wire:click="addRedirect" class="btn btn-ghost btn-sm"><i class="fa-solid fa-plus"></i></button>
            </div>
            @if(!empty($redirects))
            <div class="glass" style="overflow:hidden;">
                <table class="lp-table">
                    <thead><tr><th>Desde</th><th>Hacia</th><th>Código</th><th style="text-align:right">Acción</th></tr></thead>
                    <tbody>
                        @foreach($redirects as $i => $r)
                        <tr wire:key="redirect-{{ $i }}">
                            <td><code style="color:var(--warning);">{{ $r['from'] }}</code></td>
                            <td style="font-size:12px;">{{ $r['to'] }}</td>
                            <td><span class="badge badge-muted">{{ $r['code'] }}</span></td>
                            <td style="text-align:right;"><button wire:click="removeRedirect({{ $i }})" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        <div style="text-align:right;">
            <button wire:click="savePageRules" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove><i class="fa-solid fa-rocket"></i> Guardar Page Rules y Recargar Nginx</span>
                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Guardando...</span>
            </button>
        </div>
    </div>
    @endif

    @endif {{-- end domainId --}}

    <div wire:loading.delay class="lp-loading-toast">
        <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
    </div>
</div>
