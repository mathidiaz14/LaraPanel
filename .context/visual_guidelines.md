# Guía Visual y Estándares de LaraPanel (UI/UX)

Este documento define las reglas de diseño, estructura y clases CSS que deben seguirse al crear o modificar las vistas (Blade/Livewire) en LaraPanel. El objetivo es mantener una estética consistente, moderna (Glassmorphism Dark Theme) y **100% responsiva**.

## 1. Estructura Base de una Página

Toda vista principal debe estar envuelta en un `<div>` (requerimiento de Livewire 3) y estructurarse de la siguiente manera:

```html
<div>
    {{-- 1. Encabezado de Página --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Título de la Sección</h1>
            <p class="page-subtitle">Descripción breve de lo que hace esta sección.</p>
        </div>
        <div class="page-actions">
            <!-- Botones de acción principales -->
            <button class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nuevo</button>
        </div>
    </div>

    {{-- 2. Alertas y Mensajes --}}
    @if($successMessage)
        <div class="alert alert-success"><i class="fa-solid fa-check"></i> {{ $successMessage }}</div>
    @endif

    {{-- 3. Contenido Principal (Grid Responsivo) --}}
    <div class="lp-two-col"> <!-- Usar lp-one-col, lp-two-col, o lp-three-col -->
        
        {{-- Paneles/Tarjetas (Glassmorphism) --}}
        <div class="glass lp-panel">
            <h2 class="panel-title">
                <i class="fa-solid fa-cube text-accent"></i> Título del Panel
            </h2>
            <div class="panel-body">
                <!-- Contenido (Formularios, Tablas, Textos) -->
            </div>
        </div>
        
    </div>
</div>
```

## 2. Sistema de Rejillas (Grids) Responsivas

NUNCA usar anchos fijos (`width: 500px`) o floats. Utilizar siempre las clases de grid predefinidas:

*   `.lp-one-col`: 1 columna (100% ancho). Usado para tablas largas o editores.
*   `.lp-two-col`: 2 columnas en desktop, 1 columna en móvil. Ideal para Formularios a la izquierda y Listas a la derecha.
*   `.lp-three-col`: 3 columnas en desktop, 2 en tablet, 1 en móvil. Para tarjetas de métricas o resúmenes.

## 3. Tarjetas y Paneles (Glassmorphism)

Todo el contenido debe agruparse en paneles de cristal para respetar el tema oscuro:

*   **Contenedor**: `<div class="glass lp-panel">`
*   **Título del Panel**: `<h2 class="panel-title">` (Siempre usar un icono de FontAwesome junto al texto).
*   Para modales o elementos que deben resaltar sobre el fondo principal, usar `.glass-elevated`.

## 4. Botones (`.btn`)

Los botones deben utilizar flexbox internamente para alinear iconos y texto.
*   **Primario:** `.btn.btn-primary` (Acciones principales: Crear, Guardar).
*   **Peligro:** `.btn.btn-danger` (Eliminar, Desactivar).
*   **Fantasma / Secundario:** `.btn.btn-ghost` (Cancelar, Refrescar, Acciones menores).
*   **Pequeño:** Añadir `.btn-sm` para botones dentro de tablas.

## 5. Formularios

Agrupar cada campo en un `.form-group`:

```html
<div class="form-group">
    <label class="form-label">Nombre del Campo</label>
    <input type="text" wire:model="campo" class="form-input" placeholder="Ej: admin">
    @error('campo') <div class="form-error">{{ $message }}</div> @enderror
</div>
```
*   Para inputs con prefijos (como `https://`), usar `.input-prefix-wrap`.
*   Para checkboxes/toggles, usar la estructura predefinida de toggles en `larapanel.css`.

## 6. Tablas Responsivas

Las tablas son el principal problema de responsividad. Toda tabla debe envolverse en un contenedor con `overflow-x: auto`:

```html
<div class="table-responsive"> <!-- Clase crucial para móviles -->
    <table class="lp-table">
        <thead>
            <tr>...</tr>
        </thead>
        <tbody>
            <tr>...</tr>
        </tbody>
    </table>
</div>
```
*   Usar `.lp-row-actions` para alinear los botones de acción a la derecha dentro de la última celda (`<td>`).

## 7. Modales

Los modales deben ocupar toda la pantalla, bloquear el scroll de fondo y estar centrados:

```html
<div class="lp-modal-backdrop"> <!-- Fondo oscuro semitransparente -->
    <div class="lp-modal glass-elevated"> <!-- Tarjeta del modal -->
        <div class="lp-modal-header">...</div>
        <div class="lp-modal-body">...</div>
        <div class="lp-modal-footer">...</div>
    </div>
</div>
```

## 8. Reglas de Accesibilidad y UX

*   **Feedback Inmediato:** Usar `wire:loading` en botones de submit para evitar doble envío e indicar que el sistema está trabajando.
*   **Íconos consistentes:** Usar FontAwesome Solid (`fa-solid`).
*   **Colores semánticos:** `var(--accent)`, `var(--success)`, `var(--danger)`, `var(--warning)`. No usar colores crudos (como `#ff0000`).
