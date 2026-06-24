# Reglas de Desarrollo y Convenciones (LaraPanel)

El código del proyecto respeta estrictamente convenciones modernas del ecosistema Laravel (TALL Stack sin Alpine invasivo, delegando gran parte del estado a Livewire).

## 1. Convenciones de Sistema y Seguridad
- **Cero `shell_exec()` Crudos:** Está terminantemente prohibido usar `shell_exec()` o similares. Toda interacción con la consola Linux se hace con el wrapper `ShellExecutor` o `SudoExecutor` (para prevenir vulnerabilidades RCE y soportar modo multi-servidor SSH).
- **Parámetros Seguros:** Todos los comandos pasados al ShellExecutor DEBEN ser un `array` (ej. `['nginx', '-t']`) para aprovechar el binding nativo de `Symfony\Process` que escapa argumentos.
- **Whitelist Restrictiva:** Todo binario nuevo que deba llamarse con privilegios (como `goaccess` o `mysql`) DEBE registrarse en el array de `config/larapanel.php` -> `security.allowed_sudo_commands` o será bloqueado silenciosamente.
- **Path Traversal Shield:** Cualquier manipulación de archivos que involucre inputs del usuario debe validar la ruta resultante usando el método base `FileService::resolvePath($path)`.
- **Trazabilidad Obligatoria:** Todas las acciones destructivas (Create/Update/Delete) sobre recursos de infraestructura (Nginx, MySQL) deben dejar una huella en el modelo `AuditLog`.

## 2. Convenciones de Nomenclatura y Arquitectura
- **Inyección de Servicios:** La lógica no debe residir en Livewire. Los componentes Livewire inyectan dependencias de Servicios (`SslService`, `DomainService`) en sus constructores.
- **Entornos (Production vs Local):** Se debe chequear `app()->isProduction()` en los Servicios. En modo local/dev, el sistema no debería ejecutar comandos peligrosos, sino devolver responses dummy.
- **Archivos y Vistas:** Livewire components se nombran por su dominio en el Panel (ej. `DomainIndex.php`, `DomainCreate.php`). Sus vistas usan `kebab-case` agrupadas por carpeta funcional (`resources/views/livewire/domains/`).

## 3. Manejo de Errores y UI (Glassmorphism)
- **Bloques Try-Catch Estrictos:** Dado que el Shell puede devolver errores impredecibles, se requiere usar `try/catch (\Throwable $e)` en todo Livewire component que invoque al shell, enviando un Alert o un Log detallado en el backend y asignando `$errorMessage` para visualización frontend.
- **Componentes Full-Page (SPA feel):** Navegar en el panel no recarga el navegador.
- **Sistema de Diseño Base:** Usar tokens nativos en clases Tailwind: `.glass` (backdrop-blur + fondo traslúcido), `.glass-elevated` (paneles prominentes), `.btn-primary` (para botones interactivos). NO incorporar Bootstrap ni frameworks CSS pesados.
- **Streaming Livewire (`$this->stream()`):** Tareas asíncronas pesadas (como Unzip o Terminal) envían chunks HTML iterativos sin requerir Polling ni WebSockets directos.
