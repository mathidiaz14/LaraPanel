<div>
    {{-- Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="fa-solid fa-bug" style="color:var(--accent-light);"></i> Páginas de Error</h1>
            <p class="page-subtitle">Personaliza las páginas de error HTTP (401, 403, 404, 500, 502, 503) que sirve Nginx para tus dominios.</p>
        </div>
    </div>

    {{-- Alerts --}}
    @if($successMessage)
        <div class="alert alert-success" style="margin-bottom:20px;"><i class="fa-solid fa-circle-check"></i> {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}</div>
    @endif

    {{-- Domain Selector --}}
    <div class="glass lp-panel" style="margin-bottom:20px;">
        <h2 class="panel-title"><i class="fa-solid fa-globe" style="color:var(--accent-light);"></i> Selecciona un Dominio</h2>
        <label class="form-label">Dominio</label>
        <select wire:model.live="domainId" class="form-input" style="max-width:460px;">
            <option value="">— Selecciona un dominio —</option>
            @foreach($domains as $domain)
                <option value="{{ $domain->id }}">{{ $domain->name }}</option>
            @endforeach
        </select>
        @if($domainId === null)
            <div class="form-error" style="margin-top:8px;">Selecciona un dominio para empezar.</div>
        @endif
    </div>

    {{-- Per-code editors --}}
    @foreach($fields as $code => $meta)
        <div class="glass lp-panel" style="margin-bottom:16px;">
            <h2 class="panel-title" style="display:flex;align-items:center;gap:10px;">
                <span class="badge badge-info" style="background:rgba(56,189,248,0.15);color:#38bdf8;font-family:monospace;">{{ $code }}</span>
                <span>{{ $meta['label'] }}</span>
            </h2>
            <div class="form-group" style="margin-bottom:0;">
                <textarea wire:model="{{ $meta['prop'] }}" rows="6" class="form-input"
                    style="width:100%;font-family:ui-monospace,'Cascadia Code',monospace;font-size:13px;resize:vertical;"
                    placeholder="HTML en línea (ej. &lt;h1&gt;Página no encontrada&lt;/h1&gt;), o ruta absoluta / URL (ej. /var/www/errors/404.html, https://cdn.ejemplo.com/404.html)"></textarea>
                @error($meta['prop']) <div class="form-error">{{ $message }}</div> @enderror
            </div>
        </div>
    @endforeach

    {{-- Preview hint --}}
    <div class="glass" style="padding:var(--sp-3) var(--sp-4);margin-bottom:20px;display:flex;align-items:center;gap:12px;">
        <i class="fa-solid fa-circle-info" style="color:var(--info);font-size:16px;flex-shrink:0;"></i>
        <p style="font-size:12px;color:var(--text-secondary);margin:0;">
            El HTML inline se envuelve automáticamente en una página HTML autónoma con estilos base. Las rutas o URLs absolutas
            se usan tal cual como <code class="badge" style="font-size:11px;">error_page</code> de Nginx. Tras guardar, la configuración del dominio se re-despliega al instante.
        </p>
    </div>

    {{-- Actions --}}
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button wire:click="save" class="btn btn-primary">
            <i class="fa-solid fa-floppy-disk"></i> Guardar páginas de error
        </button>
        <button wire:click="clear" onclick="return confirm('¿Seguro que deseas eliminar todas las páginas de error personalizadas?')" class="btn btn-secondary" style="color:var(--danger);">
            <i class="fa-solid fa-trash"></i> Restablecer a las predeterminadas
        </button>
    </div>
</div>