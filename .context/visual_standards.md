# LaraPanel — Estándares Visuales y de Diseño

> **Propósito:** Este documento es la fuente de verdad para el frontend de LaraPanel.
> Toda nueva vista **debe** seguir estas especificaciones. Su objetivo es garantizar
> consistencia visual, accesibilidad y responsividad en todos los módulos.

---

## 1. Sistema de Diseño — Variables CSS

Todas las propiedades visuales se controlan exclusivamente mediante variables CSS
declaradas en `/public/css/larapanel.css`. **Nunca se deben escribir valores
hexadecimales, tamaños o colores literales en los templates Blade.**

```css
/* Colores semánticos */
--accent:          #6366f1   /* Primario / Índigo */
--accent-light:    #818cf8   /* Variante clara */
--accent-glow:     rgba(99,102,241,0.25)
--success:         #10b981   /* Verde esmeralda */
--warning:         #f59e0b   /* Ámbar */
--danger:          #ef4444   /* Rojo */
--info:            #3b82f6   /* Azul */

/* Fondos */
--bg-base:         #0a0e1a
--bg-surface:      #0f1628
--bg-elevated:     #141c35
--glass-bg:        rgba(255,255,255,0.04)
--glass-border:    rgba(255,255,255,0.08)
--glass-hover:     rgba(255,255,255,0.07)

/* Tipografía */
--text-primary:    #f1f5f9
--text-secondary:  #94a3b8
--text-muted:      #475569

/* Layout */
--sidebar-width:   260px
--topbar-height:   64px
--radius:          12px
--radius-sm:       8px
--radius-lg:       18px
--transition:      0.2s ease
```

---

## 2. Jerarquía de Layout

```
body
└── .app-layout          (flex, full height)
    ├── .sidebar          (260px fija, posición fixed)
    └── .main-content     (margin-left: 260px, flex 1)
        └── .topbar       (64px, posición fixed)
            └── .page     (padding: 28px — contenido de la ruta actual)
```

**Regla:** El contenido de cada componente Livewire va siempre dentro de `.page`.
Nunca añadir `padding` propio al `<div>` raíz del componente.

---

## 3. Estructura de Página Estándar

Cada página sigue **exactamente** esta estructura:

```blade
<div> {{-- Raíz del componente Livewire --}}

    {{-- ① ENCABEZADO DE PÁGINA --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-icon" style="color:var(--accent-light)"></i>
                Título del Módulo
            </h1>
            <p class="page-subtitle">Descripción breve del propósito de la página.</p>
        </div>
        {{-- Botón de acción principal (opcional) --}}
        <button class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Acción Principal
        </button>
    </div>

    {{-- ② ALERTAS (solo cuando hay mensajes activos) --}}
    @if($successMessage)
    <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i> {{ $successMessage }}
    </div>
    @endif
    @if($errorMessage)
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i> {{ $errorMessage }}
    </div>
    @endif

    {{-- ③ FILA DE ESTADÍSTICAS (cuando aplica) --}}
    <div class="stats-row">
        <div class="stat-card glass accent">...</div>
        <div class="stat-card glass success">...</div>
    </div>

    {{-- ④ BARRA DE FILTROS (cuando aplica) --}}
    <div class="filters-bar">
        <div class="lp-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input wire:model.live.debounce.300ms="search" type="text"
                   class="form-input" placeholder="Buscar...">
        </div>
        {{-- Botones de filtro --}}
    </div>

    {{-- ⑤ CONTENIDO PRINCIPAL --}}
    {{-- Variante A: Grid dos columnas (formulario + lista) --}}
    <div class="lp-two-col">
        <div class="glass lp-panel">...</div>
        <div class="glass lp-panel">...</div>
    </div>

    {{-- Variante B: Tabla completa --}}
    <div class="glass" style="overflow:hidden;">
        <table class="lp-table">...</table>
    </div>

    {{-- Variante C: Con Tabs --}}
    <div class="lp-tabs">
        <button class="lp-tab active">Tab 1</button>
        <button class="lp-tab">Tab 2</button>
    </div>
    <div class="glass lp-panel">...</div>

    {{-- ⑥ MODALES (al final del componente) --}}
    @if($showModal)
    <div class="lp-modal-backdrop">
        <div class="lp-modal glass-elevated">
            <div class="lp-modal-header">
                <h3>...</h3>
                <button class="lp-modal-close">...</button>
            </div>
            <div class="lp-modal-body">...</div>
            <div class="lp-modal-footer">...</div>
        </div>
    </div>
    @endif

    {{-- ⑦ INDICADOR DE CARGA (siempre al final) --}}
    <div wire:loading.delay class="lp-loading-toast">
        <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
    </div>

</div>
```

---

## 4. Clases CSS de Uso Obligatorio

### 4.1 Tipografía de Página

| Clase | Uso | Estilos |
|---|---|---|
| `.page-title` | `<h1>` — título de la página | `font-size:20px; font-weight:700` |
| `.page-subtitle` | `<p>` — descripción bajo el título | `color:var(--text-secondary); font-size:13px` |
| `.section-title` | `<h3>` — título de sección interna | `font-size:16px; font-weight:700; border-bottom` |
| `.panel-title` | `<h2>` — título de panel/card | `font-size:15px; font-weight:700` |

**Nunca** usar `<h1 style="font-size:20px;font-weight:700">`.
Siempre usar `<h1 class="page-title">`.

### 4.2 Layout

| Clase | Descripción | Breakpoints |
|---|---|---|
| `.lp-two-col` | Grid 1fr + 2fr (sidebar formulario + contenido) | Stack a 1 columna en `≤ 1024px` |
| `.lp-three-col` | Grid repeat(3, 1fr) | Stack a 2 col en `≤ 1024px`, 1 col en `≤ 768px` |
| `.stats-row` | Grid repeat(auto-fit, minmax(160px,1fr)) | Siempre responsivo |
| `.lp-panel` | Padding interior estándar (24px) para cards `.glass` | — |

### 4.3 Tablas

**REGLA CRÍTICA:** Usar **siempre** `class="lp-table"`. La clase `table` de Bootstrap
**no existe** en este proyecto y produce tablas sin estilo.

```blade
<div class="glass" style="overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="lp-table">
            <thead>
                <tr>
                    <th>Columna</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <tr wire:key="row-{{ $item->id }}">
                    <td>...</td>
                    <td style="text-align:right">
                        <div class="lp-row-actions">
                            <button class="btn btn-ghost btn-sm">...</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

### 4.4 Botones

| Clase | Color / Uso |
|---|---|
| `.btn.btn-primary` | Acento violeta — acción principal |
| `.btn.btn-secondary` | Gris — acción secundaria neutra |
| `.btn.btn-ghost` | Transparente — acciones auxiliares |
| `.btn.btn-danger` | Rojo — acciones destructivas |
| `.btn.btn-sm` | Tamaño reducido (tablas, toolbars) |
| `.btn-icon` | Solo icono, `width:34px; height:34px; justify-content:center` |

### 4.5 Badges

```blade
<span class="badge badge-success">Activo</span>
<span class="badge badge-danger">Error</span>
<span class="badge badge-warning">Pendiente</span>
<span class="badge badge-accent">Info</span>
<span class="badge badge-muted">—</span>
```

### 4.6 Formularios

```blade
<div class="form-group">
    <label class="form-label" for="campo">Etiqueta</label>
    <input id="campo" type="text" class="form-input" wire:model="campo">
    @error('campo')
    <div class="form-error">{{ $message }}</div>
    @enderror
</div>
```

**Inputs con prefijo** (ej. prefijo de BD):
```blade
<div class="input-prefix-wrap">
    <span class="input-prefix">{{ $prefix }}</span>
    <input type="text" class="form-input" placeholder="sufijo">
</div>
```

---

## 5. Patrones de Componente

### 5.1 Estadísticas (Stat Cards)

Usar las clases existentes `.stat-card` + `.glass`:

```blade
<div class="stats-row">
    <div class="stat-card glass accent">
        <i class="fa-solid fa-icon stat-icon"></i>
        <div class="stat-label">ETIQUETA</div>
        <div class="stat-value">{{ $value }}</div>
        <div class="stat-sub">Descripción adicional</div>
    </div>
</div>
```

**Colores disponibles:** `accent` | `success` | `warning` | `danger`

### 5.2 Empty State

```blade
<div class="empty-state">
    <i class="fa-solid fa-icon empty-state-icon"></i>
    <p class="empty-state-text">No tienes elementos configurados.</p>
    <button class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> Crear primero
    </button>
</div>
```

### 5.3 Modal

```blade
@if($showModal)
<div class="lp-modal-backdrop">
    <div class="lp-modal glass-elevated">
        <div class="lp-modal-header">
            <h3 class="panel-title">
                <i class="fa-solid fa-icon" style="color:var(--accent-light);margin-right:8px;"></i>
                Título del Modal
            </h3>
            <button wire:click="closeModal" class="lp-modal-close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="lp-modal-body">
            {{-- Contenido --}}
        </div>
        <div class="lp-modal-footer">
            <button wire:click="closeModal" class="btn btn-ghost">Cancelar</button>
            <button wire:click="confirm" class="btn btn-primary">Confirmar</button>
        </div>
    </div>
</div>
@endif
```

### 5.4 Tabs

```blade
<div class="lp-tabs">
    <button wire:click="$set('activeTab','a')"
            class="lp-tab {{ $activeTab === 'a' ? 'active' : '' }}">
        <i class="fa-solid fa-icon"></i> Pestaña A
    </button>
    <button wire:click="$set('activeTab','b')"
            class="lp-tab {{ $activeTab === 'b' ? 'active' : '' }}">
        <i class="fa-solid fa-icon"></i> Pestaña B
    </button>
    {{-- Acciones al lado derecho de los tabs --}}
    <div class="lp-tabs-actions">
        <button class="btn btn-ghost btn-sm">Acción</button>
    </div>
</div>
```

### 5.5 Búsqueda con Icono

```blade
<div class="lp-search">
    <i class="fa-solid fa-magnifying-glass lp-search-icon"></i>
    <input wire:model.live.debounce.300ms="search"
           type="text" class="form-input" placeholder="Buscar...">
</div>
```

### 5.6 Toast de Carga (siempre al final del componente)

```blade
<div wire:loading.delay class="lp-loading-toast">
    <i class="fa-solid fa-spinner fa-spin"></i> Procesando...
</div>
```

---

## 6. Reglas de Responsividad

### Breakpoints

| Tamaño | Breakpoint | Comportamiento |
|---|---|---|
| Desktop | `> 1024px` | Layout completo con sidebar |
| Tablet | `769px – 1024px` | Stats en 2 columnas, grids stack |
| Mobile | `≤ 768px` | Sidebar oculta (overlay), todo en 1 columna |

### Reglas obligatorias:
- **Nunca** usar `grid-template-columns:repeat(5,1fr)` ni `repeat(4,1fr)` sin `auto-fit/minmax` o un media query acompañante
- **Siempre** usar `.stats-row` (con `auto-fit minmax`) para filas de stats
- **Siempre** usar `.lp-two-col` para el patrón formulario + lista
- Las tablas deben estar envueltas en `<div style="overflow-x:auto;">` para scroll horizontal en mobile
- Los botones de acción de `.page-header` deben ser `width:100%` en mobile (el CSS ya lo hace para `.page-header .btn`)

---

## 7. Reglas Anti-Patrón (Prohibido)

❌ **Prohibido:**
```blade
{{-- ❌ Estilos inline para clases que ya existen --}}
<h1 style="font-size:20px;font-weight:700">Título</h1>

{{-- ❌ Clase "table" (no existe en este proyecto) --}}
<table class="table">

{{-- ❌ Grid fijo sin responsividad --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px">

{{-- ❌ Colores hardcoded --}}
<div style="color:#10b981">

{{-- ❌ PHP @php inline para lógica compleja en templates --}}
@php
    $ts = $totalSize; $tu = ['B','KB','MB','GB'];
    for($ti=0; $ts>=1024 && $ti<3; $ti++) $ts/=1024;
@endphp
```

✅ **Correcto:**
```blade
{{-- ✅ Clase semántica --}}
<h1 class="page-title">Título</h1>

{{-- ✅ Tabla con clase correcta --}}
<table class="lp-table">

{{-- ✅ Grid responsivo --}}
<div class="stats-row">

{{-- ✅ Variable CSS --}}
<div style="color:var(--success)">

{{-- ✅ Lógica en el componente Livewire / Accessor del modelo --}}
{{ $backup->sizeFormatted() }}
```

---

## 8. Iconos

Se usa **Font Awesome 6.5 Solid** (`fa-solid`). Guía de iconos por módulo:

| Módulo | Icono |
|---|---|
| Dashboard | `fa-gauge-high` |
| Dominios | `fa-globe` |
| SSL | `fa-lock` |
| Bases de Datos | `fa-database` |
| Email | `fa-envelope` |
| Firewall | `fa-shield-halved` |
| Backups | `fa-box-archive` |
| Cron | `fa-clock` |
| Docker | `fa-brands fa-docker` |
| DNS | `fa-network-wired` |
| Logs | `fa-scroll` |
| Terminal | `fa-terminal` |
| PHP | `fa-code` |
| Git | `fa-brands fa-git-alt` |
| WordPress | `fa-brands fa-wordpress` |
| FTP | `fa-folder-open` |
| Fail2ban | `fa-user-slash` |
| Antispam | `fa-filter` |
| Antivirus | `fa-shield-virus` |

---

## 9. Clases a Agregar al CSS (Pendientes de Implementar)

Las siguientes clases **deben añadirse** a `larapanel.css` durante la fase de migración.
Son el vocabulario mínimo que los templates Blade usarán:

```
.page-title        → <h1> de página
.page-subtitle     → <p> descripción de página
.section-title     → <h3> sección interna
.panel-title       → <h2> interior de card
.stats-row         → Grid responsivo para stat cards
.lp-two-col        → Grid 1fr 2fr responsivo
.lp-three-col      → Grid 3 columnas responsivo
.lp-panel          → Padding interior de cards (24px)
.lp-tabs           → Contenedor de pestañas
.lp-tab            → Botón de pestaña individual
.lp-tabs-actions   → Acciones alineadas a la derecha en tabs
.lp-search         → Wrapper de input con icono de búsqueda
.lp-search-icon    → Icono dentro del wrapper de búsqueda
.lp-modal-backdrop → Overlay oscuro de modal
.lp-modal          → Contenedor del modal
.lp-modal-header   → Header del modal (título + botón cerrar)
.lp-modal-body     → Cuerpo del modal
.lp-modal-footer   → Footer del modal (botones)
.lp-modal-close    → Botón X para cerrar modal
.lp-loading-toast  → Toast de "Procesando..." (wire:loading)
.lp-row-actions    → Wrapper flex para acciones en filas de tabla
.empty-state       → Contenedor de estado vacío centrado
.empty-state-icon  → Icono grande en estado vacío
.empty-state-text  → Texto descriptivo en estado vacío
.input-prefix-wrap → Wrapper para inputs con prefijo
.input-prefix      → Prefijo de texto en input
.btn-secondary     → Botón variante gris neutral
.btn-icon          → Botón cuadrado solo icono
.form-error        → Texto de error de validación
```
