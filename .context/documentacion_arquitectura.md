# Documentación de Arquitectura (LaraPanel)

## 1. Stack Tecnológico
- **Backend:** Laravel 11.x (PHP 8.3).
- **Frontend Reactivo:** Livewire 4.x (SSR + streaming `wire:stream`).
- **UI Interactiva:** Alpine.js 3.x, TailwindCSS (con Design System nativo de variables CSS), Chart.js 4.4, Monaco Editor.
- **Capa del OS:** Nginx (o Apache), PHP-FPM, Postfix, Dovecot, MySQL, UFW, ClamAV, Docker, PowerDNS, Fail2ban, acme.sh.
- **Shell / SSH:** `Symfony\Process` localmente y `phpseclib3` para ejecución remota.

## 2. Patrón de Arquitectura y Capas
LaraPanel implementa una **Arquitectura en Capas Monolítica Orientada a Servicios**:

1. **Capa Cliente (Navegador):** Componentes visuales de Livewire/Alpine actualizando el DOM mediante un protocolo JSON vía HTTP/WebSockets.
2. **Capa de Control (Livewire Components):** En vez de Controladores estándar, los Componentes Livewire (`app/Livewire/`) enrutan la acción y actúan de ViewModel.
3. **Capa de Negocio (Services):** Hay más de 23 clases en `app/Services/` (`DomainService`, `FirewallService`, etc.) que encapsulan la lógica pura y manipulan los modelos Eloquent.
4. **Capa de Ejecución (Shell Layer):** Todas las acciones que requieren hablar con Linux pasan por aquí. Se abstraen a través del `ServerContext`.

### Patrón Singleton `ServerContext`
- Determina el entorno operativo (Local vs Remoto SSH).
- En entorno local, invoca `SudoExecutor` (ejecución mediante `sudo -n`).
- En entorno remoto, invoca `RemoteShellExecutor` (ejecución SSH).
- Ambas clases comparten la misma API fluida (`run()`, `withTimeout()`, `inDirectory()`).

## 3. Estructura de Directorios Real
```
/
├── app/
│   ├── Livewire/        # UI (Dashboard, Domains, Files, Admin, etc.)
│   ├── Services/        # Lógica de infraestructura subyacente (23 servicios).
│   ├── Models/          # Entidades Eloquent (27 modelos: Domain, DnsZone, etc).
│   ├── Shell/           # SudoExecutor, RemoteShellExecutor, ServerContext.
│   ├── Http/Controllers/# Rutas API y Webhooks (ej: GitWebhookController).
│   └── Jobs/            # Tareas asíncronas de fondo (Queue).
├── bootstrap/           # Inicio del framework Laravel.
├── config/              # Archivos de config, en especial `larapanel.php`.
├── database/            # Migraciones (esquemas base) y `database.sqlite` actual.
├── resources/
│   ├── css/             # `larapanel.css` (variables root, .glass, utility classes).
│   └── views/livewire/  # Plantillas Blade de Livewire.
├── routes/
│   └── web.php          # Definición de URLs y asimilación de Livewire full-page.
└── *.sh                 # Scripts raiz: install.sh, update.sh, install-dns.sh, etc.
```

## 4. Autenticación, Seguridad y Variables
- **Gestión Auth:** Laravel Fortify y Sanctum. `Require2FA` listo para middleware TOTP.
- **Roles y Permisos:** `Spatie/laravel-permission` separa accesos de `admin`, `reseller` y `client`.
- **Ejecución Controlada:** `SudoExecutor` cuenta con un sistema de **Whitelist Estricta** en `config/larapanel.php` (ej. solo permite ejecutar binarios aprobados como `nginx`, `certbot`, `ufw`). Previene RCE (Remote Code Execution).
- **Protección Path Traversal:** El gestor de archivos valida todas las rutas mediante `FileService::resolvePath()` confinándolas en `/var/www/`.
- **Variables de Entorno (`.env`):** Configuraciones principales como `APP_ENV`, `LARAPANEL_WEBSERVER`, `PDNS_API_URL` (PowerDNS local) y `RSPAMD_API_URL`. Las variables se inyectan dinámicamente o actúan por default en `config/larapanel.php`.
