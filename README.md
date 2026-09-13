<div align="center">

**Una alternativa open-source a cPanel, construida con Laravel 13 + Livewire 4**

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4.x-4E56A6?style=flat-square&logo=livewire)](https://livewire.laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=flat-square&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)
[![Version](https://img.shields.io/badge/Version-0.1.0--alpha-orange?style=flat-square)](CHANGELOG.md)

</div>

---

## ¿Qué es LaraPanel?

LaraPanel es un panel de control de servidores web de código abierto, **construido completamente en Laravel y Livewire**, que permite gestionar todos los aspectos de un VPS o servidor dedicado desde una interfaz web moderna y reactiva. Es una alternativa completa a cPanel/WHM, Plesk o Hestia, pensada para desarrolladores y agencias que quieren control total sobre su infraestructura sin pagar licencias costosas.

### Características principales

- 🎨 **Interfaz Glassmorphism con Design System** — Diseño premium, oscuro y reactivo con actualizaciones en tiempo real vía Livewire y una base de estilos unificada aplicada a todas las vistas
- 🔐 **Multi-tenant** — Soporte para múltiples usuarios (Admin, Reseller, Cliente) con planes de hosting configurables
- 🛡️ **Seguridad avanzada** — 2FA obligatoria para administradores (TOTP + Passkeys/WebAuthn), firewall (UFW), Fail2ban, Antivirus ClamAV, Antispam Rspamd, registros de auditoría y cuotas de disco
- 🤖 **Automatización completa** — SSL automático (incluyendo wildcard DNS-01), despliegues Git con webhooks, auto-provisión de registros DNS, auto-monitoreo de uptime, backups programados y notificaciones Telegram
- 🚀 **Rendimiento por dominio** — Under Attack Mode, microcaché FastCGI, Geo-WAF, proxy reverso Orange Cloud, Page Rules, HSTS, Brotli y reportes GoAccess
- 🔌 **API REST** — Compatible con WHMCS, Blesta y otros sistemas de facturación vía Laravel Sanctum
- ⚡ **Tiempo real** — Monitoreo de CPU, RAM, disco y red en vivo con Chart.js y terminal web con sesiones PTY por WebSocket

---

## Módulos disponibles

### 🌐 Dominios y Web

| Módulo | Descripción |
|---|---|
| **Dominios / Subdominios** | Gestión completa de hosts virtuales Nginx/Apache con soporte para dominios principales, subdominios, addon y parqueados. Añade, edita, suspende y elimina dominios. Configura PHP por dominio. **Auto-provisión de registros DNS A** al crear subdominios. |
| **SSL / TLS** | Emisión de certificados gratuitos con **Let's Encrypt** (vía acme.sh), **wildcard DNS-01** que cubre todos los subdominios, auto-renovación diaria/noche y soporte para certificados externos y autofirmados con validación de par/clave/dominio. |
| **PHP Multi-versión** | Gestión de múltiples versiones de PHP-FPM (8.1, 8.2, 8.3, 8.4). Cambia la versión activa por dominio en un clic, reinicia pools por versión y edita directivas `php.ini` de forma segura mediante archivos de anulación. |
| **WordPress Manager** | Instalación automatizada de WordPress vía WP-CLI (core, `wp-config`, permalinks) y listado de plugins instalados. |
| **Performance por Dominio** | Ajustes avanzados de Nginx por dominio: **Under Attack Mode** (rate/conn limits), **Microcaché FastCGI**, **Geo-WAF** (bloqueo por país con base de datos MaxMind), **Orange Cloud** (proxy reverso con soporte WebSocket), **Page Rules**, **HSTS/preload**, headers personalizados, **Brotli**, redirects 301/302 e **informe GoAccess** en iframe seguro. |

### 📧 Email y Mensajería

| Módulo | Descripción |
|---|---|
| **Email Completo** | Creación y gestión de cuentas de correo virtual (Postfix + Dovecot). Cuotas de almacenamiento, suspensión instantánea, generador de contraseñas seguras, importación masiva desde ZIP. |
| **Webmail (Roundcube)** | Acceso web integrado a correos mediante Roundcube Webmail configurado automáticamente para cada dominio bajo la URL `webmail.tudominio.com`, con **auto-login mediante URL firmada** (sin credenciales duplicadas). |
| **Alias y Reenvíos** | Gestión de redirecciones múltiples y catch-alls con validación de destinos. |
| **Autoresponders** | Respuestas automáticas programables por intervalos de fechas. |
| **DKIM Manager** | Generación automática de llaves DKIM, publicación en DNS (PowerDNS), configuración de políticas SPF/DMARC y **verificación de todos los registros del dominio**. |
| **Estadísticas de Email** | Monitoreo de mensajes en cola, tasa de rebote y actividad del servidor de correo. |
| **Antispam (Rspamd)** | Panel de configuración de Rspamd, reglas personalizadas de puntuación, listas blancas/negras por IP/correo/dominio, historial y estadísticas, test de mensajes y flush de Bayes. |

### 🗄️ Bases de Datos

| Módulo | Descripción |
|---|---|
| **Bases de Datos MySQL** | Creación de bases de datos y usuarios con permisos configurados. Cambio de contraseñas, actualización de tamaño y eliminación segura. Exportación e importación vía interfaz web. |
| **phpMyAdmin SSO** | Acceso directo a phpMyAdmin desde el panel con **SSO en un clic**, generando tokens temporales con credenciales de base de datos que se limpian automáticamente cada 5 minutos. |

### 📁 Archivos

| Módulo | Descripción |
|---|---|
| **File Manager Avanzado** | Explorador de archivos Split-Pane con **favoritos**, **menú contextual (clic derecho)**, accesos rápidos y operaciones múltiples. Creación, edición, renombrado, copia, movimiento, eliminación, compresión/descompresión ZIP con streaming. |
| **Papelera (Trash)** | Eliminación segura con **papelera virtual**: los archivos borrados van a una papelera con manifiesto, desde donde pueden restaurarse o purgarse definitivamente. |
| **Editor Monaco** | Editor de código integrado con resaltado de sintaxis para PHP, JS, HTML, CSS, JSON, Bash y más. |
| **Gestión de Permisos** | Cambio de permisos octales (`chmod`), propiedad de archivos y subida/descarga segura, con protección anti path-traversal incluyendo symlinks. |

### 🔒 Seguridad

| Módulo | Descripción |
|---|---|
| **2FA con Passkeys** | Autenticación de dos factores con código TOTP **y Passkeys/WebAuthn** (Fortify). **Obligatoria para administradores**. |
| **Firewall (UFW)** | Gestión de reglas de firewall con presets predefinidos (SSH, HTTP, HTTPS, SMTP, etc.). Bloqueo y apertura de puertos con un clic, reglas por servidor/usuario y estadísticas de conexiones. |
| **Fail2ban** | Monitoreo de jaulas activas, IPs baneadas, desbanes por jaula o globales, restarts y tail de logs en tiempo real. |
| **Antivirus (ClamAV)** | Escaneo de directorios con ClamAV, **cuarentena automática** de archivos infectados (listar/restaurar/eliminar), escaneos en background, historial por usuario y actualización de definiciones (freshclam) desde el panel. |
| **Auditoría** | Registro de auditoría completo (`audit_logs`) con severidad (info/warning/critical) y retención configurable. |
| **Cuotas y ciclo de vida** | Cálculo del uso de disco por usuario (webroots + bases de datos + buzones), enforce de cuota y **logout forzado** (web + sesiones Reverb) al suspender cuentas o cambiar roles. |
| **Impersonación** | Admin y Resellers pueden **impersonar** (iniciar sesión como) cualquier usuario para diagnosticar problemas. |

### 🔧 DevOps

| Módulo | Descripción |
|---|---|
| **Git Deploy** | Despliegues automáticos vía **webhooks Git** con endpoints únicos por proyecto y **botón de prueba del webhook**. Elige rama o commit específico, ejecuta comandos post-deploy (artisan migrate, npm build, etc.), **force-update** y auto-deploys desacoplados (detached). Log de despliegues con navegación completa entre commits. |
| **Docker Manager** | Gestión de contenedores (listar, start, stop, restart, delete, ver logs, exec), imágenes (pull, remove, prune) y stacks de Docker Compose (deploy con YAML, down, logs, custom commands). Estadísticas con `docker system df`. |
| **FTP Manager** | Creación de cuentas FTP aisladas por directorio con límites avanzados: cuota de espacio, ancho de banda, conexiones simultáneas e IPs permitidas/denegadas. Modo solo lectura. |
| **FTP Remoto** | Copia de sitios completos desde **servidores FTP/FTPS remotos** con lftp: conexión, navegación, descarga con staging y **jobs de mirror en background** con logs descargables. |
| **Cron Jobs** | Gestor visual de tareas programadas con expresiones cron estándar y selectores de intervalo. Ejecución en vivo en background con captura de salida, timeout y contadores de éxito/fallo. |
| **Backups** | Creación manual y **programada** (daily/weekly/monthly) de backups completos (archivos + DB) con retención configurable. Drivers de almacenamiento **local, S3 y SFTP**. Historial con fecha/tamaño/estado, descarga directa y restauración desde el panel. |
| **DNS Manager** | Gestión completa de zonas DNS (**PowerDNS**). Editor de registros A, AAAA, CNAME, MX, TXT, SRV, CAA, PTR, SOA y ALIAS, con plantilla de email (SPF/DKIM/DMARC) y validación de contenido. |
| **Terminal Web** | Terminal interactiva en el navegador con **sesiones PTY reales por WebSocket (Xterm.js)**: comandos no-interactivos con whitelist estricta y sesiones interactivas con auditoría de comandos, límite de sesiones concurrentes e idle timeout. |
| **Control de Procesos / Servicios / Red / Disco** | Secciones de administración para **matar procesos** (ordenar por CPU/memoria, kill normal/forzado), **gestionar servicios systemd** (start/stop/restart/reload), ver **puertos en escucha y conexiones** con resolución de PID/usuario, y **escanear el uso de disco** por directorio y partición. |
| **Gestión Cluster (Multi-Servidor)** | Conecta múltiples servidores remotos (nodos) mediante SSH agentless con credenciales cifradas (clave o password). Selector global en navbar, estadísticas de recursos y **terminal SSH remota dedicada** por nodo. |

### 📊 Monitoreo y Logs

| Módulo | Descripción |
|---|---|
| **Super Dashboard** | Vista unificada con gráficas en tiempo real de CPU, RAM y Disco (Chart.js), estado de servicios (Nginx, MySQL, PHP-FPM, Redis), carga y procesos top. |
| **Uptime Monitoring** | **Auto-monitoreo de uptime** para dominios y contenedores Docker: chequeo cada minuto, monitorización HTTP/Docker en vivo, historial de pings, % de uptime y alertas con cooldown. Auto-enrolamiento de monitores para recursos nuevos. |
| **Visor de Logs** | Interfaz estilo terminal para leer logs del sistema (`laravel.log`, `syslog`, `auth.log`, `nginx/error.log`, `fail2ban.log`) y logs de dominios individuales. Filtrado en tiempo real, tail y limpieza de archivos. |

### 👑 Administración Multi-tenant

| Módulo | Descripción |
|---|---|
| **Planes de Hosting** | Define planes comerciales con límites de dominios, email, bases de datos, disco y ancho de banda. Habilita o deshabilita módulos premium por plan. |
| **Gestión de Usuarios** | Crea usuarios con roles (Admin, Reseller, Cliente). Asigna planes, suspende cuentas con 1 clic e **impersona** para soporte. |
| **Ajustes Globales** | Panel de configuración con **notificaciones Telegram configurables por tipo** (logins, umbrales de recursos, uptime, backups y más — 11 tipos) y **botón de envío de mensaje de prueba**. |
| **API Tokens (Sanctum)** | Genera Bearer Tokens con expiración y registro de último uso para conectar con WHMCS, Blesta u otros sistemas de facturación. |

### 🔌 API REST

Endpoints disponibles bajo `/api/v1/`:

```
POST   /v1/accounts/create          → Crear cuenta de cliente
POST   /v1/accounts/{id}/suspend    → Suspender cuenta
POST   /v1/accounts/{id}/unsuspend  → Reactivar cuenta
DELETE /v1/accounts/{id}            → Eliminar cuenta y datos
```

Autenticación: `Bearer Token` vía Laravel Sanctum.

---

## Stack Tecnológico

| Componente | Tecnología |
|---|---|
| Backend Framework | Laravel 13 |
| Componentes reactivos | Livewire 4 |
| Autenticación | Laravel Fortify + Sanctum (TOTP + Passkeys/WebAuthn) |
| Roles y Permisos | Spatie Laravel Permission |
| Colas de trabajos | Laravel Horizon |
| WebSockets | Laravel Reverb |
| Terminal web | Xterm.js + PTY por WebSocket |
| Base de datos | MySQL 8 (producción) / SQLite (desarrollo) |
| Gráficas | Chart.js |
| Editor de código | Monaco Editor |
| Gestión SSL | acme.sh + Certbot (HTTP-01 y wildcard DNS-01) |
| Gestión de contenedores | Docker Engine / Docker Compose |
| Motor Antivirus | ClamAV + freshclam |
| Motor Antispam | Rspamd + Redis (Bayes) |
| Servidor DNS | PowerDNS Authoritative Server + SQLite3 |
| Cliente Webmail | Roundcube Webmail + SQLite3 |
| FTP Remoto | lftp (mirror en background) |
| Geo-WAF | MaxMind GeoLite2 (MMDB) |
| Analítica web | GoAccess |
| Gestión Cluster | phpseclib3 (SSH2 agentless) |
| Interfaz de servidor | sudo + ShellExecutor seguro |

---

## Requisitos del Sistema

### Servidor (Producción)

| Recurso | Mínimo | Recomendado |
|---|---|---|
| OS | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |
| RAM | 2 GB | 4 GB |
| Disco | 20 GB SSD | 40 GB SSD |
| vCPUs | 2 | 4 |
| PHP | 8.3 | 8.3 |
| MySQL | 8.0 | 8.0 |
| Node.js | 20.x | 22.x |

> ⚠️ **Importante:** LaraPanel debe instalarse en un servidor **limpio y dedicado**. No es compatible con otros paneles de control (cPanel, Plesk, Hestia, etc.).

### Entorno de Desarrollo (Local)

- PHP 8.3+
- Composer 2.x
- Node.js 20+
- SQLite (incluido, no requiere configuración extra)
- Docker & Docker Compose (opcional, requerido para usar el módulo Docker)

> 💡 Fuera de producción, el panel ejecuta servicios en **modo simulación**, por lo que es totalmente demoable sin privilegios de root.

---

## Instalación en VPS (Producción)

### Método automatizado (recomendado)

LaraPanel incluye un instalador completo. Ejecuta en tu VPS Ubuntu como root:

```bash
# 1. Subir los archivos al VPS (desde tu máquina local)
rsync -avz --exclude='.git' --exclude='node_modules' --exclude='vendor' \
  /ruta/local/panel/ root@IP_DEL_VPS:/root/larapanel/

# 2. Conectarte al VPS
ssh root@IP_DEL_VPS

# 3. Ejecutar el instalador
cd /root/larapanel
sudo bash install.sh
```

El script interactivo te pedirá:
- **Dominio** del panel (ej: `panel.tudominio.com`)
- **Email** del administrador
- **Contraseña** del panel
- **Contraseña** para MySQL
- Si deseas instalar **SSL automático** con Let's Encrypt

El instalador gestiona automáticamente:

1. ✅ Actualización del sistema
2. ✅ Instalación de Nginx
3. ✅ PHP 8.3 + extensiones + versiones adicionales (8.1, 8.2)
4. ✅ MySQL 8 + creación de DB y usuario
5. ✅ Node.js 22 + Composer
6. ✅ Instalación de Docker Engine y Docker Compose
7. ✅ Instalación de ClamAV + actualización inicial de definiciones + cron diario
8. ✅ Instalación de Rspamd + Redis + configuración de Bayes y API con contraseña
9. ✅ Usuario del sistema `larapanel` (asignado a grupos `docker` y `www-data`)
10. ✅ Despliegue de archivos
11. ✅ Configuración de `.env` para producción
12. ✅ Migraciones de base de datos
13. ✅ Creación del usuario administrador
14. ✅ Configuración de `sudoers` (permisos de sistema)
15. ✅ Virtual host Nginx
16. ✅ SSL con Let's Encrypt (Certbot)
17. ✅ Supervisor (workers de colas persistentes)
18. ✅ Firewall UFW
19. ✅ acme.sh para gestión SSL interna

---

## Instalación en Desarrollo Local

```bash
# 1. Clonar el repositorio
git clone https://github.com/tu-usuario/larapanel.git
cd larapanel

# 2. Instalar dependencias
composer install
npm install

# 3. Configurar el entorno
cp .env.example .env
php artisan key:generate

# 4. Crear la base de datos SQLite y ejecutar migraciones
touch database/database.sqlite
php artisan migrate --seed

# 5. Crear el usuario administrador
php artisan tinker
>>> \App\Models\User::factory()->create(['email' => 'admin@larapanel.local', 'password' => bcrypt('LaraPanel2024!')]);
>>> \App\Models\User::first()->assignRole('admin');

# 6. Compilar assets y arrancar el servidor
npm run build
php artisan serve --port=8080
# O en modo desarrollo con hot-reload:
composer run dev
```

**Acceso local:**
```
URL:       http://127.0.0.1:8080
Email:     admin@larapanel.local
Password:  LaraPanel2024!
```

---

## Configuración Post-instalación

### Configurar sudoers (crítico para producción)

LaraPanel utiliza un `SudoExecutor` interno que ejecuta comandos privilegiados con `sudo -n`. El archivo `/etc/sudoers.d/larapanel` (creado por el instalador) otorga permisos al usuario `www-data` para gestionar:

- `nginx` — Recargar/reiniciar el servidor web
- `php*-fpm` — Gestionar pools de PHP por versión
- `mysql` — Operaciones de base de datos vía CLI
- `systemctl` — Control de servicios del sistema
- `ufw` / `iptables` — Reglas de firewall
- `fail2ban-client` — Gestión de baneo de IPs
- `certbot` / `acme.sh` — Gestión de certificados SSL
- Operaciones de filesystem (`chmod`, `chown`, `mkdir`, `rm`, etc.)

### Configurar el Scheduler (cron)

El instalador configura Supervisor para ejecutar el scheduler automáticamente. Si prefieres cron tradicional:

```bash
crontab -e -u larapanel
# Agregar:
* * * * * cd /var/www/larapanel && php artisan schedule:run >> /dev/null 2>&1
```

Tareas programadas incluidas:

- **Cada minuto** — Chequeo de monitores de uptime (`larapanel:uptime`)
- **Cada 5 minutos** — Métricas del servidor (`panel:collect-metrics`), chequeo de uptime de dominios con alertas (`panel:check-uptime`), limpieza de tokens SSO phpMyAdmin
- **Horario** — Auto-enrolamiento de monitores Uptime (`larapanel:uptime-sync`), backups programados (`backups:run-scheduled`)
- **3:00 AM** — Auto-renovación de certificados SSL (acme.sh)
- **Diario** — Limpieza de métricas antiguas del servidor

### Variables de entorno relevantes

```dotenv
# Aplicación
APP_ENV=production          # local | production
APP_DEBUG=false             # Siempre false en producción
APP_URL=https://panel.tu-dominio.com
APP_TIMEZONE=America/Montevideo

# Base de datos (MySQL en producción)
DB_CONNECTION=mysql
DB_DATABASE=larapanel_db
DB_USERNAME=larapanel
DB_PASSWORD=tu_password_seguro

# Colas y caché
QUEUE_CONNECTION=database   # Usar 'redis' para mayor rendimiento
CACHE_STORE=database

# Notificaciones Telegram
TELEGRAM_BOT_TOKEN=         # API token del bot (opcional, desde el panel)
TELEGRAM_CHAT_ID=           # ID del chat/grupo (opcional, desde el panel)

# WebSockets (Livewire Reverb)
REVERB_APP_ID=larapanel
REVERB_APP_KEY=tu-key
REVERB_APP_SECRET=tu-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080

LARAPANEL_VERSION=0.1.0
```

---

## Arquitectura del Proyecto

```
/panel
├── app/
│   ├── Livewire/                   ← Componentes reactivos (42 componentes)
│   │   ├── Dashboard.php           ← Super Dashboard con métricas en tiempo real
│   │   ├── Profile.php             ← Perfil, 2FA (TOTP + Passkeys), sesiones
│   │   ├── Admin/                  ← Planes, usuarios, API tokens y ajustes globales
│   │   ├── Antispam/               ← Panel de Rspamd y reglas de spam
│   │   ├── Antivirus/              ← Escaneos ClamAV y cuarentena
│   │   ├── Backups/                ← Backups locales/S3/SFTP y programados
│   │   ├── Cron/                   ← Gestor de tareas cron
│   │   ├── DiskUsage/              ← Consumo de disco por directorio
│   │   ├── DNS/                    ← Zonas DNS y editor de registros
│   │   ├── Databases/              ← Gestión de bases de datos MySQL
│   │   ├── Docker/                 ← Contenedores, imágenes y Compose
│   │   ├── Domains/                ← Dominios y configuración Nginx/Apache
│   │   ├── Email/                  ← Cuentas, alias, DKIM, autoresponders, stats
│   │   ├── FTP/                    ← Cuentas FTP
│   │   ├── RemoteFtp/              ← Copia de sitios desde FTP/FTPS remoto
│   │   ├── Fail2ban/               ← Monitoreo de jaulas y baneo de IPs
│   │   ├── Files/                  ← File Manager con papelera y Monaco Editor
│   │   ├── Firewall/               ← Reglas UFW / iptables
│   │   ├── Git/                    ← Despliegues automáticos por webhook
│   │   ├── Logs/                   ← Visor de logs del sistema
│   │   ├── Network/                ← Puertos y conexiones activas
│   │   ├── Performance/            ← Under Attack, microcaché, Geo-WAF, Orange Cloud, GoAccess
│   │   ├── PHP/                    ← PHP multi-versión y configuración FPM
│   │   ├── Processes/              ← Control de procesos del sistema
│   │   ├── Services/               ← Gestión de servicios systemd
│   │   ├── SSL/                    ← Let's Encrypt (wildcard) y certs externos
│   │   ├── Servers/                ← Servidores remotos y selector global
│   │   ├── Terminal/               ← Terminal web con sesiones PTY por WebSocket
│   │   ├── Uptime/                 ← Auto-monitoreo de uptime
│   │   ├── WordPress/              ← Instalador WordPress con WP-CLI
│   │   └── ...
│   │
│   ├── Services/                   ← Lógica de negocio y comandos del sistema (36 servicios)
│   │   ├── DomainService.php       ← Provisiona vhosts Nginx/Apache + performance
│   │   ├── SslService.php          ← Let's Encrypt (acme.sh) y certs externos
│   │   ├── PhpService.php          ← Gestión de pools PHP-FPM y php.ini
│   │   ├── DatabaseService.php     ← Operaciones MySQL seguras vía sudo CLI
│   │   ├── FileService.php         ← Filesystem: chmod, chown, zip, papelera
│   │   ├── EmailService.php        ← Buzones virtuales Postfix/Dovecot
│   │   ├── DkimService.php         ← Generación y publicación de llaves DKIM
│   │   ├── DnsService.php          ← Gestión de zonas PowerDNS
│   │   ├── AntispamService.php     ← Configuración de Rspamd
│   │   ├── AntivirusService.php    ← Escaneos ClamAV y cuarentena
│   │   ├── FirewallService.php     ← Reglas UFW e iptables
│   │   ├── Fail2banService.php     ← Jaulas, baneos y eventos
│   │   ├── BackupService.php       ← Backups en local/S3/SFTP
│   │   ├── CronService.php         ← Sincronización al crontab de Linux
│   │   ├── GitService.php          ← Ejecución de deploys y webhooks
│   │   ├── MonitoringService.php   ← Métricas de CPU, RAM, disco y red
│   │   ├── LogService.php          ← Lectura segura de logs del sistema
│   │   ├── TerminalService.php     ← Ejecución de comandos en Terminal Web
│   │   ├── TerminalSessionManager.php ← Sesiones PTY interactivas por WebSocket
│   │   ├── FtpService.php          ← Cuentas FTP y permisos
│   │   ├── RemoteFtpService.php    ← Mirror de sitios vía lftp
│   │   ├── QuotaService.php        ← Cálculo y enforce de cuotas de disco
│   │   ├── ForceLogoutService.php  ← Cierre de sesiones al suspender cuentas
│   │   ├── Notifier.php            ← Notificaciones Telegram configurables
│   │   ├── UptimeProvisioner.php   ← Auto-enrolamiento de monitores de uptime
│   │   ├── GeoWafService.php       ← Geo-bloqueo por país (MaxMind)
│   │   ├── GoAccessService.php     ← Reportes de analítica web
│   │   ├── ServerService.php       ← Nodos remotos SSH multi-servidor
│   │   ├── WordPressService.php    ← Instalación WordPress vía WP-CLI
│   │   └── ...
│   │
│   ├── Shell/
│   │   ├── ShellExecutor.php       ← Abstracción segura de comandos shell
│   │   ├── SudoExecutor.php        ← Comandos privilegiados con sudo -n
│   │   └── ShellResult.php         ← Modelo de resultado de comando
│   │
│   ├── Models/                     ← 34 modelos Eloquent
│   ├── Http/Controllers/Api/       ← API REST (AccountController)
│   └── Console/Commands/           ← SslRenewCertificates, CheckUptime, etc.
│
├── routes/
│   ├── web.php                     ← Rutas del panel (clientes + admin)
│   ├── api.php                     ← API v1 (Sanctum)
│   └── console.php                 ← Scheduler de tareas
│
├── resources/views/livewire/       ← Vistas Blade para cada módulo
├── database/migrations/            ← 38 migraciones
├── config/
│   ├── larapanel.php               ← Configuración global del panel
│   └── filesystems.php             ← Disco dinámico 'user_files'
│
└── install.sh                      ← Instalador automatizado para VPS
```

---

## Comandos Útiles

```bash
# Desarrollo
composer run dev               # Arranca servidor + queue + vite + logs en paralelo
php artisan migrate:fresh --seed  # Reset completo de la DB

# Producción
php artisan optimize           # Cachear config, rutas, vistas y eventos
php artisan optimize:clear     # Limpiar todos los cachés
php artisan queue:work         # Iniciar worker de colas (Supervisor lo gestiona)
supervisorctl status           # Ver estado de workers
supervisorctl restart larapanel-worker:*  # Reiniciar workers

# Utilidades del panel
php artisan ssl:renew            # Forzar renovación de todos los SSL
php artisan panel:collect-metrics    # Recopilar métricas del servidor
php artisan larapanel:uptime         # Ejecutar chequeo de uptime
php artisan larapanel:uptime-sync    # Auto-enrolar monitores de uptime
php artisan backups:run-scheduled    # Ejecutar backups programados
php artisan cron:run {job}           # Ejecutar un job cron manualmente
php artisan git:deploy {deployment}  # Ejecutar un deploy Git
php artisan tinker              # Consola interactiva de Laravel
```

---

## Seguridad

LaraPanel implementa múltiples capas de seguridad:

- **Autenticación** — Laravel Fortify con **2FA obligatoria para administradores** (TOTP + Passkeys/WebAuthn), timeout de sesión y límite de intentos de login
- **Autorización** — Spatie Permissions con roles granulares (Admin, Reseller, Cliente)
- **Comandos del sistema** — Lista blanca estricta de comandos permitidos en `SudoExecutor` y `TerminalCommandPolicy` (sin operadores de shell, redirección ni sustitución; bloqueo de `--privileged`/`--root`/`bash -c`)
- **Anti path-traversal** — Validación estricta de rutas en `FileService`, `LogService` y `DiskUsageService`, incluyendo resolución de symlinks
- **Sesiones del terminal** — Límite de sesiones concurrentes, idle timeout y **auditoría de comandos** en base de datos
- **API** — Tokens Sanctum con scopes, expiración y último uso. Los tokens se muestran una sola vez al crearlos
- **Cifrado** — Llaves privadas SSL **y credenciales de servidores remotos** cifradas en base de datos con el APP_KEY de Laravel
- **Registro de auditoría** — Trazabilidad completa de acciones con severidad y retención configurable
- **phpMyAdmin SSO** — Credenciales temporales escritas en `/tmp` con permisos restrictivos y auto-limpieza

Para reportar vulnerabilidades, abre un issue privado.

---

## Licencia

LaraPanel es software de código abierto publicado bajo la [Licencia MIT](LICENSE).

---

<div align="center">

Construido con ❤️ sobre [Laravel](https://laravel.com) y [Livewire](https://livewire.laravel.com)

</div>