<div>
<div style="max-width:740px;margin:0 auto;">

    {{-- Header --}}
    <div class="page-header" style="justify-content: flex-start; gap: 14px;">
        <a href="{{ route('ssl.index') }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-file-import" style="color:var(--accent-light);"></i> Instalar Certificado Propio
            </h1>
            <p class="page-subtitle">
                Pega tu certificado SSL comprado (PEM format) para instalarlo en el servidor.
            </p>
        </div>
    </div>

    {{-- Success --}}
    @if($success)
    <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:12px;padding:28px;text-align:center;">
        <i class="fa-solid fa-circle-check" style="font-size:42px;color:var(--success);display:block;margin-bottom:14px;"></i>
        <div style="font-size:16px;font-weight:700;margin-bottom:8px;">¡Certificado instalado!</div>
        <div style="font-size:13px;color:var(--text-secondary);margin-bottom:18px;">{{ $successMsg }}</div>
        <a href="{{ route('ssl.index') }}" class="btn btn-primary"><i class="fa-solid fa-lock"></i> Ver certificados</a>
    </div>
    @else

    {{-- Error --}}
    @if($errorMsg)
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMsg }}</div>
    @endif

    {{-- Cert preview card --}}
    @if(!empty($certInfo))
    <div style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:10px;padding:16px;margin-bottom:20px;">
        <div style="font-size:12px;font-weight:600;color:var(--accent-light);margin-bottom:10px;">
            <i class="fa-solid fa-magnifying-glass"></i> Información del certificado detectada
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;">
            @foreach([
                'Emisor'     => ($certInfo['issuer']['O'] ?? $certInfo['issuer']['CN'] ?? '—'),
                'Válido desde' => ($certInfo['valid_from'] ?? '—'),
                'Expira'     => ($certInfo['valid_to'] ?? '—'),
                'Algoritmo'  => ($certInfo['algorithm'] ?? '—'),
                'CN'         => ($certInfo['subject']['CN'] ?? '—'),
            ] as $label => $value)
            <div style="padding:8px 12px;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:7px;">
                <div style="font-size:10px;color:var(--text-muted);margin-bottom:2px;">{{ strtoupper($label) }}</div>
                <div style="font-size:12px;font-weight:500;word-break:break-all;">{{ $value }}</div>
            </div>
            @endforeach
        </div>
        @if(!empty($certInfo['san']))
        <div style="margin-top:10px;font-size:11px;color:var(--text-muted);">
            <strong>SAN:</strong> {{ $certInfo['san'] }}
        </div>
        @endif
    </div>
    @endif

    <div class="glass lp-panel">

        {{-- Domain selector --}}
        <div class="form-group">
            <label class="form-label">Dominio destino <span style="color:var(--danger)">*</span></label>
            <select wire:model="domainId" class="form-input">
                <option value="">-- Selecciona un dominio --</option>
                @foreach($domains as $domain)
                <option value="{{ $domain->id }}">{{ $domain->name }}</option>
                @endforeach
            </select>
            @error('domainId') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        {{-- Certificate --}}
        <div class="form-group">
            <label class="form-label">
                Certificado (CRT/PEM) <span style="color:var(--danger)">*</span>
                <span style="font-weight:400;color:var(--text-muted);">— incluir BEGIN/END CERTIFICATE</span>
            </label>
            <textarea wire:model.live="certificate"
                      class="form-input"
                      rows="7"
                      style="font-family:monospace;font-size:12px;resize:vertical;"
                      placeholder="-----BEGIN CERTIFICATE-----
MIIFazCCA1OgAwIBAgIRAIIQz7DSQONZRGPgu2OCiwAwDQYJKoZIhvcNAQELBQAw
...
-----END CERTIFICATE-----"></textarea>
            @error('certificate') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        {{-- Private Key --}}
        <div class="form-group">
            <label class="form-label">
                Llave Privada (KEY/PEM) <span style="color:var(--danger)">*</span>
                <span style="font-weight:400;color:var(--text-muted);">— se encripta en la base de datos</span>
            </label>
            <textarea wire:model="privateKey"
                      class="form-input"
                      rows="7"
                      style="font-family:monospace;font-size:12px;resize:vertical;"
                      placeholder="-----BEGIN PRIVATE KEY-----
MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQC2...
-----END PRIVATE KEY-----"></textarea>
            @error('privateKey') <div class="form-error">{{ $message }}</div> @enderror
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                <i class="fa-solid fa-shield-halved" style="color:var(--success);"></i>
                La llave privada se encripta con AES-256 antes de almacenarse.
            </div>
        </div>

        {{-- CA Chain --}}
        <div class="form-group">
            <label class="form-label">
                Cadena CA / Bundle <span style="color:var(--text-muted);font-weight:400;">— opcional pero recomendado</span>
            </label>
            <textarea wire:model="caChain"
                      class="form-input"
                      rows="5"
                      style="font-family:monospace;font-size:12px;resize:vertical;"
                      placeholder="-----BEGIN CERTIFICATE-----
(Certificado intermedio de tu CA)
-----END CERTIFICATE-----"></textarea>
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                Necesario si tu CA requiere certificados intermedios (ej: Sectigo, DigiCert, Comodo).
            </div>
        </div>

        {{-- Validation warning --}}
        <div style="background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.2);border-radius:8px;padding:12px 14px;margin-bottom:20px;font-size:12px;color:var(--text-secondary);">
            <i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);margin-right:6px;"></i>
            LaraPanel verificará que la llave privada corresponde al certificado y que el cert es válido para el dominio seleccionado antes de instalarlo.
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:8px;border-top:1px solid var(--glass-border);">
            <a href="{{ route('ssl.index') }}" class="btn btn-ghost">Cancelar</a>
            <button wire:click="install"
                    wire:loading.attr="disabled"
                    class="btn btn-primary">
                <span wire:loading.remove><i class="fa-solid fa-file-import"></i> Instalar certificado</span>
                <span wire:loading><i class="fa-solid fa-spinner fa-spin"></i> Validando e instalando...</span>
            </button>
        </div>
    </div>

    {{-- Guide --}}
    <div style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="glass" style="padding:14px;">
            <div style="font-size:12px;font-weight:600;color:var(--accent-light);margin-bottom:8px;">
                <i class="fa-brands fa-cloudflare"></i> Cloudflare Origin
            </div>
            <p style="font-size:12px;color:var(--text-secondary);line-height:1.7;">
                Si usas Cloudflare: SSL → Origin Server → Create Certificate. Pega el origin cert y la private key aquí.
            </p>
        </div>
        <div class="glass" style="padding:14px;">
            <div style="font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:8px;">
                <i class="fa-solid fa-building"></i> Cert de tu CA (Sectigo, etc.)
            </div>
            <p style="font-size:12px;color:var(--text-secondary);line-height:1.7;">
                Pega el archivo <code>.crt</code> en Certificado, el <code>.key</code> en Llave Privada, y el bundle <code>.ca-bundle</code> en Cadena CA.
            </p>
        </div>
    </div>
    @endif
</div>
</div>
