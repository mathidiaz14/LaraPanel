# LaraPanel — Documentación de Arquitectura

## 1. Visión General

LaraPanel es un panel de control de servidor web tipo cPanel/Plesk, auto-hospedado, construido sobre Laravel 11. Se comunica directamente con el sistema operativo Linux del servidor a través de una capa de ejecución de comandos privilegiada y controlada.

---

## 2. Stack Tecnológico Detallado

| Componente | Tecnología | Versión |
|-----------|-----------|---------|
| Framework backend | Laravel | 11.x |
| Frontend reactivo | Livewire | 4.3+ |
| Interactividad JS | Alpine.js | 3.x |
| CSS | Design system propio (variables CSS) | — |
| Ejecución de comandos | Symfony Process | — |
| SSH remoto | phpseclib3 | 3.x |
| Autenticación | Laravel Fortify + Sanctum | — |
| Roles/Permisos | Spatie Laravel-Permission | — |
| Charts | Chart.js | 4.4 |
| Editor código | Monaco Editor (vía iframe) | — |
| Base de datos | MySQL (producción) / SQLite (dev) | — |
| Cola de trabajos | Laravel Queue (database driver) | — |
| Cache | Laravel Cache (file/redis) | — |

---

## 3. Arquitectura de Capas

```
┌─────────────────────────────────────────────────────────────┐
│                      NAVEGADOR (Cliente)                    │
│          Alpine.js + Livewire JS (WebSocket/HTTP)           │
└──────────────────────────┬──────────────────────────────────┘
                           │ HTTP / Livewire wire protocol
┌──────────────────────────▼──────────────────────────────────┐
│                   LARAVEL APPLICATION                        │
│  ┌─────────────┐  ┌─────────────┐  ┌──────────────────────┐ │
│  │  Routes     │  │ Livewire    │  │ Http Controllers      │ │
│  │ web.php     │→ │ Components  │  │ (Webhooks, API)       │ │
│  └─────────────┘  └──────┬──────┘  └──────────────────────┘ │
│                          │                                   │
│                   ┌──────▼──────┐                           │
│                   │  Services   │ ← lógica de negocio       │
│                   │  (23 clases)│                           │
│                   └──────┬──────┘                           │
│          ┌───────────────┼───────────────┐                  │
│          ▼               ▼               ▼                  │
│  ┌──────────────┐ ┌───────────┐ ┌──────────────┐           │
│  │ Eloquent ORM │ │   Shell   │ │ External APIs │           │
│  │  (27 models) │ │  Layer    │ │ PowerDNS/etc. │           │
│  └──────┬───────┘ └─────┬─────┘ └──────────────┘           │
└─────────┼───────────────┼──────────────────────────────────┘
          │               │
          ▼               ▼
  ┌────────────┐   ┌──────────────────────────────────┐
  │   MySQL    │   │       LINUX OS                   │
  │  Database  │   │  nginx, php-fpm, postfix,        │
  └────────────┘   │  dovecot, mysql, ufw, clamav,    │
                   │  docker, powerdns, fail2ban       │
                   └──────────────────────────────────┘
```

---

## 4. Capa Shell — Ejecución de Comandos

### 4.1 Jerarquía de clases

```
ShellExecutor          ← Wrapper de Symfony Process. Sin shell interpolation.
    │
    └── SudoExecutor   ← Prepende "sudo -n". Valida whitelist de comandos.

RemoteShellExecutor    ← SSH via phpseclib3. Misma API que ShellExecutor.
```

### 4.2 Flujo de ejecución local (`SudoExecutor`)

```
Service::método()
  → SudoExecutor::run(['nginx', '-t'])
      → ShellExecutor::validateCommand()   ← verifica whitelist
          → new Symfony\Process(['sudo','-n','nginx','-t'])
              → ShellExecutor::auditBefore()   ← Log::info + AuditLog::create()
              → process->run()
              → ShellExecutor::auditAfter()    ← Log resultado
          → throw RuntimeException si exit ≠ 0 y checkExit=true
      → return ShellResult { exitCode, stdout, stderr, command }
```

### 4.3 Whitelist de comandos autorizados

Definida en `config/larapanel.php` → `security.allowed_sudo_commands`:

```
systemctl, nginx, php-fpm, mysql, certbot, ufw, fail2ban-client,
useradd, userdel, chown, chmod, crontab, docker, clamscan, freshclam,
clamdscan, rm, mv, find, mkdir, cp, ln, a2ensite, acme.sh, tar,
mysqldump, gzip, tail, truncate, ss, openssl, git, ps
```

### 4.4 Ejecución remota (`RemoteShellExecutor`)

- Usa **phpseclib3** para SSH.
- Autenticación: clave privada RSA o contraseña (según `Server.auth_type`).
- Misma interfaz fluida que `ShellExecutor`: `.withTimeout()`, `.inDirectory()`.
- Los comandos se construyen con `escapeshellarg()` para prevenir inyección.
- El contexto activo lo maneja `ServerContext::isRemote()`.

### 4.5 `ServerContext` — Patrón Singleton de contexto

```php
ServerContext::isRemote()    → bool
ServerContext::server()      → Server model
ServerContext::executor()    → RemoteShellExecutor
```

Los servicios consultan `ServerContext` al inicio para decidir si operar localmente o via SSH.

---

## 5. Capa de Componentes Livewire

### 5.1 Ciclo de vida de un request

```
1. Usuario interactúa con UI (click, input)
2. Alpine.js / Livewire JS captura el evento
3. HTTP POST a /livewire/update (payload JSON con diff de propiedades)
4. Laravel routea al Component Livewire correcto
5. Component llama al Service inyectado
6. Service ejecuta lógica → Shell → OS o DB
7. Component actualiza sus propiedades públicas
8. Livewire calcula diff del DOM
9. JSON de respuesta con instrucciones de patch del DOM
10. Livewire JS aplica los cambios en el cliente
```

### 5.2 Streaming con `$this->stream()`

Usado en el módulo de descompresión de archivos y terminal:

```php
// En el componente Livewire:
$this->stream(
    to: 'unzip-log',
    content: "<div>archivo.php</div>",
    replace: false   // append vs replace
);

// En la vista Blade:
<div wire:stream="unzip-log"></div>
```

Livewire 4 implementa streaming via **chunked HTTP response**, enviando fragmentos HTML al cliente a medida que el servidor los genera, sin necesidad de WebSockets.

### 5.3 Módulos → Componentes → Servicios

| Módulo | Componente Livewire | Servicio Principal |
|--------|--------------------|--------------------|
| Dashboard | `Dashboard.php` | `MonitoringService` |
| Dominios | `Domains/DomainIndex`, `DomainCreate` | `DomainService` |
| SSL | `SSL/SslIndex`, `SslIssue`, `SslInstall` | `SslService` |
| Archivos | `Files/FileManager` | `FileService` |
| Email | `Email/EmailIndex`, `EmailAliases`, `DkimManager`… | `EmailService`, `DkimService` |
| Bases de datos | `Databases/DatabaseIndex` | `DatabaseService` |
| FTP | `FTP/FtpIndex` | `FtpService` |
| DNS | `DNS/DnsIndex`, `DnsZoneEditor` | `DnsService` |
| Firewall | `Firewall/FirewallIndex` | `FirewallService` |
| Fail2ban | `Fail2ban/Fail2banIndex` | `Fail2banService` |
| Antispam | `Antispam/AntispamIndex` | `AntispamService` |
| Antivirus | `Antivirus/AntivirusIndex` | `AntivirusService` |
| Cron | `Cron/CronIndex` | `CronService` |
| Backups | `Backups/BackupIndex` | `BackupService` |
| Git Deploy | `Git/GitIndex` | `GitService` |
| Docker | `Docker/DockerIndex` | `DockerService` |
| PHP Manager | `PHP/PhpIndex` | `PhpService` |
| Terminal | `Terminal/TerminalIndex` | `TerminalService` |
| Logs | `Logs/LogIndex` | `LogService` |
| WordPress | `WordPress/WordPressIndex` | `WordPressService` |
| Servidores | `Servers/ServersIndex`, `ServerSelector` | `ServerService` |
| Admin | `Admin/UserIndex`, `PlanIndex`, `Settings`, `ApiTokens` | — |

---

## 6. Sistema de Autenticación y Autorización

### 6.1 Autenticación

- **Laravel Fortify**: maneja login, logout, registro, reset de contraseña.
- **Laravel Sanctum**: tokens API para acceso programático (módulo API Tokens).
- **2FA**: campo `two_factor_enabled` en `users`. Ready para implementar TOTP.
- **Spatie/Permission**: roles `admin`, `reseller`, `client`.

### 6.2 Roles y capacidades

| Rol | Capacidades |
|-----|------------|
| `admin` | Acceso total, terminal, gestión de usuarios, configuración global |
| `reseller` | Gestión de sus clientes (Phase 4 — pendiente) |
| `client` | Solo sus propios recursos, limitado por Plan |

### 6.3 Sistema de Planes (`Plan` model)

Los clientes tienen un plan asignado con cuotas:

```php
Plan {
    max_domains, max_databases, max_email_accounts,
    max_ftp_accounts, max_disk_gb, max_bandwidth_gb,
    features (JSON): ['ssl', 'backups', 'docker', 'git', ...]
}
```

El método `User::canAddDomain()` verifica contra `Plan::max_domains` antes de crear.

---

## 7. Rutas de la Aplicación

### 7.1 Rutas web autenticadas (`middleware: auth`)

Todas las rutas del panel requieren autenticación. Cada ruta devuelve directamente un componente Livewire (Full-Page Component):

```
GET /                   → Dashboard
GET /domains            → DomainIndex
GET /domains/create     → DomainCreate
GET /ssl                → SslIndex
GET /databases          → DatabaseIndex
GET /files              → FileManager
GET /email              → EmailIndex
GET /ftp                → FtpIndex
GET /dns                → DnsIndex
GET /firewall           → FirewallIndex
GET /fail2ban           → Fail2banIndex
GET /antispam           → AntispamIndex
GET /antivirus          → AntivirusIndex
GET /cron               → CronIndex
GET /backups            → BackupIndex
GET /git                → GitIndex
GET /docker             → DockerIndex
GET /wordpress          → WordPressIndex
GET /terminal           → TerminalIndex (admin only)
GET /servers            → ServersIndex
GET /logs               → LogIndex
GET /admin/*            → Admin components
```

### 7.2 Rutas públicas

```
POST /api/webhooks/git/{uuid}          → GitWebhookController::handle()
GET  /webmail/autologin/{token}        → WebmailAutoLoginController
GET  /login, /forgot-password          → Auth views
```

---

## 8. Esquema de Base de Datos

### 8.1 Tabla `users`

```sql
id, name, email, password, role (admin|reseller|client),
is_active, two_factor_enabled, two_factor_secret,
avatar, timezone, language, plan_id, last_login_at,
last_login_ip, suspended_at, suspension_reason,
email_verified_at, remember_token, timestamps
```

### 8.2 Tabla `domains`

```sql
id, user_id, name, type (main|subdomain|alias|parked),
parent_domain, document_root, php_version, webserver (nginx|apache|both),
status (pending|active|suspended|error), is_active,
ssl_enabled, deployed_at, timestamps
```

### 8.3 Tabla `git_deployments`

```sql
id, user_id, domain_name, repository_url, branch,
deploy_path, deploy_script (text), auto_deploy (bool),
webhook_secret, webhook_uuid, last_deployed_at, timestamps
```

### 8.4 Tabla `git_deployment_logs`

```sql
id, deployment_id, status (running|success|failed),
triggered_by (manual|webhook), commit_hash, commit_message,
output (longtext), timestamps
```

### 8.5 Tabla `audit_logs`

```sql
id, user_id, action (dot.notation), subject, subject_type,
subject_id, meta (JSON), ip_address, user_agent,
severity (info|warning|critical), timestamps
```

### 8.6 Tabla `firewall_rules`

```sql
id, user_id, name, action (allow|deny|limit),
port, protocol (tcp|udp|any), source_ip, destination_ip,
direction (in|out), is_active, is_preset, ufw_rule_id,
sort_order, notes, timestamps
```

### 8.7 Tabla `servers` (Multi-servidor)

```sql
id, user_id, name, hostname, port (default 22),
username, auth_type (key|password),
ssh_private_key (encrypted), ssh_password (encrypted),
os_info (JSON — cpu, ram, disk, uptime),
status (online|offline|error), last_ping_at, timestamps
```

### 8.8 Tablas adicionales

| Tabla | Propósito |
|-------|-----------|
| `ssl_certificates` | Certs con proveedor, SANs, fechas expiry, auto_renew |
| `email_accounts` | Cuentas IMAP/SMTP con quota |
| `email_aliases` | Alias de redirección |
| `email_autoresponders` | Respuestas automáticas |
| `dkim_keys` | Claves DKIM por dominio |
| `databases` | Instancias MySQL con usuario asociado |
| `ftp_accounts` | Cuentas FTP con directorio raíz |
| `dns_zones` | Zonas PowerDNS |
| `dns_records` | Registros A/AAAA/MX/CNAME/TXT/NS/SRV |
| `fail2ban_events` | Eventos de baneo con IP y jail |
| `spam_rules` | Reglas Rspamd personalizadas |
| `cron_jobs` | Expresiones cron + script |
| `cron_run_logs` | Historial de ejecuciones |
| `backups` | Metadata de backups con tamaño y estado |
| `docker_containers` | Cache de estado de contenedores |
| `antivirus_scans` | Historial de escaneos ClamAV |
| `quarantine_files` | Archivos en cuarentena |
| `plans` | Planes con cuotas JSON |
| `settings` | Config clave-valor global |
| `personal_access_tokens` | Tokens Sanctum |
| `jobs`, `cache` | Colas y caché de Laravel |

---

## 9. Integración con Servicios del Sistema Operativo

### 9.1 Nginx

- **Config path**: `/etc/nginx/sites-available/{dominio}.conf`
- **Symlinks**: `/etc/nginx/sites-enabled/{dominio}.conf`
- **Reload**: `nginx -t && systemctl reload nginx` (zero-downtime)
- **Template de vhost** generado dinámicamente por `DomainService`

### 9.2 PHP-FPM

- Pool por dominio: `/etc/php/{version}/fpm/pool.d/{dominio}.conf`
- Versiones soportadas: 8.1, 8.2, 8.3 (configurable)
- Cada dominio puede tener su propia versión PHP

### 9.3 MySQL

- Gestión via comandos `mysql` y `mysqldump` con sudo
- Prefijo de BD por usuario: primeros 8 chars del email + `_`
- Ejemplo: usuario `admin@example.com` → prefix `admin_`

### 9.4 Postfix + Dovecot

- Configuración en `/etc/postfix` y `/etc/dovecot/conf.d`
- Buzones en `/var/mail/vhosts/{dominio}/{usuario}`
- DKIM keys en `/etc/rspamd/dkim/{dominio}.key`

### 9.5 PowerDNS

- API REST en `http://127.0.0.1:8053/api/v1`
- Autenticado con `X-API-Key` header
- Configurado en `config/larapanel.php` → `powerdns`

### 9.6 SSL / Let's Encrypt

- `acme.sh` en `/root/.acme.sh/acme.sh` (preferido sobre certbot)
- Challenge: webroot HTTP-01
- Certs almacenados en `/etc/ssl/larapanel/{dominio}/`
- Auto-renovación vía cron propio de `acme.sh`

### 9.7 UFW (Firewall)

- `ufw status verbose` → parsing de output
- `ufw allow/deny/limit {port}/{protocol}`
- `ufw delete {rule_number}` para eliminar reglas
- Protección anti-lockout: SSH siempre garantizado

### 9.8 Docker

- Comunicación via socket: `/var/run/docker.sock`
- Output formateado con Go templates (`--format`)
- Comandos: `docker ps -a`, `docker start/stop/restart/rm`, `docker logs`, `docker stats`

### 9.9 ClamAV

- `clamscan --recursive --infected`
- `--move=/var/larapanel/quarantine` para cuarentena
- `freshclam` para actualización de definiciones
- Cuarentena en `/var/larapanel/quarantine`

---

## 10. Sistema de Webhooks Git

```
GitHub/GitLab → POST /api/webhooks/git/{uuid}
    → GitWebhookController::handle()
        → Verificar HMAC SHA-256 (X-Hub-Signature-256)
        → Verificar rama del push == deployment.branch
        → Verificar auto_deploy == true
        → GitService::deploy($deployment, 'webhook', $commitHash)
            → git fetch origin {branch}
            → git reset --hard origin/{branch}
            → Ejecutar deploy_script (bash) si existe
            → Guardar GitDeploymentLog con output
```

El `uuid` en la URL es único por repositorio y actúa como token de seguridad adicional.

---

## 11. Design System CSS

Archivo: `public/css/larapanel.css`

### Variables CSS globales

```css
--bg-base:       #0a0e1a   /* Fondo principal oscuro */
--bg-surface:    #0f1628   /* Superficie de cards */
--bg-elevated:   #141c35   /* Cards elevadas */
--glass-bg:      rgba(255,255,255,0.04)
--glass-border:  rgba(255,255,255,0.08)
--accent:        #6366f1   /* Indigo principal */
--accent-light:  #818cf8
--success:       #10b981
--warning:       #f59e0b
--danger:        #ef4444
--sidebar-width: 260px
--topbar-height: 64px
```

### Layout responsivo

- **Desktop**: sidebar fijo 260px + topbar + contenido principal.
- **Tablet (≤1024px)**: grids de stats 2 columnas.
- **Móvil (≤768px)**: sidebar colapsable con overlay, padding reducido, tablas con scroll horizontal, headers en columna.

### Clases de utilidad principales

```
.glass          → card con efecto glassmorphism
.glass-elevated → card más prominente
.btn-primary    → botón acento con glow
.btn-ghost      → botón transparente
.btn-danger     → botón rojo
.lp-table       → tabla del panel (responsive en móvil)
.page-header    → header de página (flex, responsive)
.filters-bar    → barra de filtros (flex, responsive)
.badge-*        → badges de colores (success/danger/warning/accent/muted)
.stat-card      → card de métrica con ícono
.fm-container   → contenedor del file manager (responsive)
```

---

## 12. Configuración de Entornos

### 12.1 Detección de entorno

```php
app()->isProduction()   → true en producción
```

En desarrollo (`APP_ENV=local`): los servicios devuelven datos simulados, no ejecutan comandos reales. Permite desarrollo sin un servidor Linux real.

### 12.2 Variables de entorno clave (`.env`)

```env
APP_ENV=production
APP_KEY=...
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=larapanel

LARAPANEL_VERSION=0.1.0-alpha
LARAPANEL_WEBSERVER=nginx
LARAPANEL_DEFAULT_PHP=8.3
LARAPANEL_SUDO_USER=www-data
SERVER_PUBLIC_IP=xxx.xxx.xxx.xxx

PDNS_ENABLED=true
PDNS_API_URL=http://127.0.0.1:8053/api/v1
PDNS_API_KEY=secret

RSPAMD_API_URL=http://127.0.0.1:11334
RSPAMD_PASSWORD=secret
```

### 12.3 Sudoers (`/etc/sudoers.d/larapanel`)

```
www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx, /bin/systemctl, /usr/bin/certbot, ...
```

El script `install-autologin.sh` configura esto automáticamente.

---

## 13. Módulos y Feature Flags

Todos los módulos son activables/desactivables en `config/larapanel.php`:

```php
'modules' => [
    'domains'     => true,
    'email'       => true,
    'databases'   => true,
    'filemanager' => true,
    'ssl'         => true,
    'dns'         => true,
    'firewall'    => true,
    'ftp'         => true,
    'cron'        => true,
    'backups'     => true,
    'terminal'    => true,
    'monitoring'  => true,
    'logs'        => true,
    'phpmanager'  => true,
    'docker'      => true,
    'antivirus'   => true,
    'resellers'   => false,   // Phase 4
    'multiserver' => true,
]
```

El layout (`app.blade.php`) usa `@if(config('larapanel.modules.X'))` para mostrar/ocultar ítems del sidebar.

---

## 14. Convenciones de Código

| Convención | Regla |
|-----------|-------|
| Comandos del sistema | Siempre via `SudoExecutor`, nunca `shell_exec` directo |
| Rutas de archivos | Siempre via `config('larapanel.paths.*')`, nunca hardcoded |
| Acciones destructivas | Siempre registrar en `AuditLog::record()` |
| Errores de shell | Siempre `try/catch` con mensaje de error descriptivo |
| Producción vs Dev | Usar `app()->isProduction()` para bifurcar comportamiento |
| Validación de paths | Siempre `FileService::resolvePath()` para paths de usuario |
| Servicios | Inyectados via constructor o método de Livewire |
