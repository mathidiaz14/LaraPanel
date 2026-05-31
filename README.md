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

- 🎨 **Interfaz Glassmorphism** — Diseño premium, oscuro y reactivo con actualizaciones en tiempo real vía Livewire
- 🔐 **Multi-tenant** — Soporte para múltiples usuarios (Admin, Reseller, Cliente) con planes de hosting configurables
- 🤖 **Automatización completa** — SSL automático, despliegues Git con webhooks, renovaciones, backups programados
- 🛡️ **Seguridad integrada** — Firewall (UFW), Fail2ban, Antispam, DKIM/SPF/DMARC desde el panel
- 🔌 **API REST** — Compatible con WHMCS, Blesta y otros sistemas de facturación vía Laravel Sanctum
- ⚡ **Tiempo real** — Monitoreo de CPU, RAM, disco y red en vivo con Chart.js

---

## Módulos disponibles

### 🌐 Dominios y Web

| Módulo | Descripción |
|---|---|
| **Dominios / Subdominios** | Gestión completa de hosts virtuales Nginx/Apache. Añade, edita, suspende y elimina dominios. Configura PHP por dominio. |
| **SSL / TLS** | Emisión de certificados gratuitos con **Let's Encrypt** (vía acme.sh), auto-renovación nocturna y soporte para certificados externos personalizados. |
| **PHP Multi-versión** | Gestión de múltiples versiones de PHP-FPM (8.1, 8.2, 8.3). Cambia la versión activa por dominio en un clic. Edita directivas `php.ini` de forma segura mediante archivos de anulación. |
| **WordPress Manager** | Instalación automatizada de WordPress vía WP-CLI, incluyendo base de datos, usuario admin y configuración de Nginx. |

### 📧 Email y Mensajería

| Módulo | Descripción |
|---|---|
| **Email Completo** | Creación y gestión de cuentas de correo virtual (Postfix + Dovecot). Cuotas de almacenamiento, suspensión instantánea, generador de contraseñas seguras. |
| **Alias y Reenvíos** | Gestión de redirecciones múltiples con validación de destinos. |
| **Autoresponders** | Respuestas automáticas programables por fechas. |
| **DKIM Manager** | Generación automática de llaves DKIM, publicación en DNS y configuración de políticas SPF/DMARC. |
| **Estadísticas de Email** | Monitoreo de mensajes en cola, tasa de rebote y actividad del servidor de correo. |
| **Antispam (Rspamd)** | Panel de configuración de Rspamd, reglas personalizadas de puntuación, listas blancas/negras y estadísticas. |

### 🗄️ Bases de Datos

| Módulo | Descripción |
|---|---|
| **Bases de Datos MySQL** | Creación de bases de datos y usuarios con permisos configurados. Cambio de contraseñas y eliminación segura. Exportación e importación vía interfaz web. Estado de PHP-FPM integrado. |

### 📁 Archivos

| Módulo | Descripción |
|---|---|
| **File Manager Avanzado** | Explorador de archivos Split-Pane con accesos rápidos. Creación, edición, renombrado, copia, eliminación, compresión/descompresión ZIP. |
| **Editor Monaco** | Editor de código integrado con resaltado de sintaxis para PHP, JS, HTML, CSS, JSON, Bash y más. |
| **Gestión de Permisos** | Cambio de permisos octales (`chmod`), propiedad de archivos y subida/descarga segura. |

### 🔒 Seguridad

| Módulo | Descripción |
|---|---|
| **Firewall (UFW)** | Gestión de reglas de firewall con presets predefinidos (SSH, HTTP, HTTPS, SMTP, etc.). Bloqueo y apertura de puertos con un clic. |
| **Fail2ban** | Monitoreo de jaulas activas, IPs baneadas, desbaneos manuales y log de eventos en tiempo real. |
| **Antivirus (ClamAV)** | Escaneo de directorios con ClamAV, cuarentena automática de archivos infectados, historial de escaneos por usuario, actualización de definiciones (freshclam) desde el panel. |

### 🔧 DevOps

| Módulo | Descripción |
|---|---|
| **Git Deploy** | Despliegues automáticos vía webhooks Git. Crea endpoints únicos para cada proyecto, elige rama y ejecuta comandos post-deploy (artisan migrate, npm build, etc.). Log de deploys con salida completa. |
| **Docker Manager** | Gestión de contenedores (listar, start, stop, restart, delete, ver logs), descargar imágenes (pull) y gestionar stacks de Docker Compose (desplegar con YAML, apagar, ver logs de stacks). |
| **FTP Manager** | Creación de cuentas FTP aisladas por directorio. Cuotas de espacio y modo solo lectura. |
| **Cron Jobs** | Gestor visual de tareas programadas con expresiones cron estándar. Selectores de intervalo predefinidos. Ejecución en vivo con captura de salida. Contadores de éxito/fallo. |
| **Backups Locales** | Creación manual y programada de backups completos (archivos + DB). Historial con fecha, tamaño y estado. Descarga directa y restauración desde el panel. |
| **DNS Manager** | Gestión completa de zonas DNS (PowerDNS). Edición de registros A, CNAME, MX, TXT, NS, SRV. |
| **Terminal Web** | Terminal interactiva en el navegador (solo Admin). Acceso shell seguro sin necesidad de SSH. |
| **Gestión Cluster (Multi-Servidor)** | Conecta múltiples servidores remotos (nodos) mediante SSH agentless. Selector global en navbar, estadísticas de recursos e interfaz de terminal SSH remota dedicada por nodo. |

### 📊 Monitoreo y Logs

| Módulo | Descripción |
|---|---|
| **Super Dashboard** | Vista unificada con gráficas en tiempo real de CPU, RAM y Disco (Chart.js), estado de servicios (Nginx, MySQL, PHP-FPM, Redis) y resumen de recursos del servidor. |
| **Visor de Logs** | Interfaz estilo terminal para leer logs del sistema (`laravel.log`, `syslog`, `auth.log`, `nginx/error.log`, `fail2ban.log`) y logs de dominios individuales. Filtrado en tiempo real y limpieza de archivos. |

### 👑 Administración Multi-tenant

| Módulo | Descripción |
|---|---|
| **Planes de Hosting** | Define planes comerciales con límites de dominios, email, bases de datos, disco y ancho de banda. Habilita o deshabilita módulos premium por plan (Terminal, Backups, etc.). |
| **Gestión de Usuarios** | Crea usuarios con roles (Admin, Reseller, Cliente). Asigna planes. Suspende cuentas con 1 clic. |
| **API Tokens (Sanctum)** | Genera Bearer Tokens para conectar con WHMCS, Blesta u otros sistemas de facturación. |

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
| Autenticación | Laravel Fortify + Sanctum |
| Roles y Permisos | Spatie Laravel Permission |
| Colas de trabajos | Laravel Horizon |
| WebSockets | Laravel Reverb |
| Base de datos | MySQL 8 (producción) / SQLite (desarrollo) |
| Gráficas | Chart.js |
| Editor de código | Monaco Editor |
| Gestión SSL | acme.sh + Certbot |
| Gestión de contenedores | Docker Engine / Docker Compose |
| Motor Antivirus | ClamAV + freshclam |
| Motor Antispam | Rspamd + Redis (Bayes) |
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
8. ✅ Despliegue de archivos
9. ✅ Configuración de `.env` para producción
10. ✅ Migraciones de base de datos
11. ✅ Creación del usuario administrador
12. ✅ Configuración de `sudoers` (permisos de sistema)
13. ✅ Virtual host Nginx
14. ✅ SSL con Let's Encrypt (Certbot)
15. ✅ Supervisor (workers de colas persistentes)
16. ✅ Firewall UFW
17. ✅ acme.sh para gestión SSL interna

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
- **3:00 AM** — Auto-renovación de certificados SSL (acme.sh)
- **Diario** — Limpieza de métricas antiguas del servidor
- **Según configuración** — Backups automáticos de dominios

### Variables de entorno relevantes

```dotenv
# Aplicación
APP_ENV=production          # local | production
APP_DEBUG=false             # Siempre false en producción
APP_URL=https://panel.tu-dominio.com

# Base de datos (MySQL en producción)
DB_CONNECTION=mysql
DB_DATABASE=larapanel_db
DB_USERNAME=larapanel
DB_PASSWORD=tu_password_seguro

# Colas y caché
QUEUE_CONNECTION=database   # Usar 'redis' para mayor rendimiento
CACHE_STORE=database

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
│   ├── Livewire/                   ← Componentes reactivos (19 módulos)
│   │   ├── Dashboard.php           ← Super Dashboard con métricas en tiempo real
│   │   ├── Admin/                  ← Gestión de planes, usuarios y API tokens
│   │   ├── Antispam/               ← Panel de Rspamd y reglas de spam
│   │   ├── Backups/                ← Backups locales y programados
│   │   ├── Cron/                   ← Gestor de tareas cron
│   │   ├── DNS/                    ← Zonas DNS y editor de registros
│   │   ├── Databases/              ← Gestión de bases de datos MySQL
│   │   ├── Domains/                ← Dominios y configuración Nginx/Apache
│   │   ├── Email/                  ← Cuentas, alias, DKIM, autoresponders
│   │   ├── FTP/                    ← Cuentas FTP
│   │   ├── Fail2ban/               ← Monitoreo de jaulas y baneo de IPs
│   │   ├── Files/                  ← File Manager avanzado con Monaco Editor
│   │   ├── Firewall/               ← Reglas UFW / iptables
│   │   ├── Git/                    ← Despliegues automáticos por webhook
│   │   ├── Logs/                   ← Visor de logs del sistema
│   │   ├── Monitoring/             ← (Reservado — integrado en Dashboard)
│   │   ├── PHP/                    ← PHP multi-versión y configuración FPM
│   │   ├── SSL/                    ← Let's Encrypt y certificados externos
│   │   ├── Terminal/               ← Terminal web (solo Admin)
│   │   └── WordPress/              ← Instalador WordPress con WP-CLI
│   │
│   ├── Services/                   ← Lógica de negocio y comandos del sistema
│   │   ├── DomainService.php       ← Provisiona vhosts Nginx/Apache
│   │   ├── SslService.php          ← Let's Encrypt (acme.sh) y certs externos
│   │   ├── PhpService.php          ← Gestión de pools PHP-FPM y php.ini
│   │   ├── DatabaseService.php     ← Operaciones MySQL seguras vía sudo CLI
│   │   ├── FileService.php         ← Filesystem: chmod, chown, zip, unzip
│   │   ├── EmailService.php        ← Buzones virtuales Postfix/Dovecot
│   │   ├── DkimService.php         ← Generación y publicación de llaves DKIM
│   │   ├── DnsService.php          ← Gestión de zonas PowerDNS
│   │   ├── AntispamService.php     ← Configuración de Rspamd
│   │   ├── FirewallService.php     ← Reglas UFW e iptables
│   │   ├── Fail2banService.php     ← Jaulas, baneos y eventos
│   │   ├── BackupService.php       ← Backups de archivos y bases de datos
│   │   ├── CronService.php         ← Sincronización al crontab de Linux
│   │   ├── GitService.php          ← Ejecución de deploys y webhooks
│   │   ├── MonitoringService.php   ← Métricas de CPU, RAM, disco y red
│   │   ├── LogService.php          ← Lectura segura de logs del sistema
│   │   ├── TerminalService.php     ← Ejecución de comandos en Terminal Web
│   │   ├── FtpService.php          ← Cuentas FTP y permisos
│   │   └── WordPressService.php    ← Instalación WordPress vía WP-CLI
│   │
│   ├── Shell/
│   │   ├── ShellExecutor.php       ← Abstracción segura de comandos shell
│   │   ├── SudoExecutor.php        ← Comandos privilegiados con sudo -n
│   │   └── ShellResult.php         ← Modelo de resultado de comando
│   │
│   ├── Models/                     ← 22 modelos Eloquent
│   ├── Http/Controllers/Api/       ← API REST (AccountController)
│   └── Console/Commands/           ← SslRenewCertificates, SyncDomains, etc.
│
├── routes/
│   ├── web.php                     ← 30+ rutas del panel
│   ├── api.php                     ← API v1 (Sanctum)
│   └── console.php                 ← Scheduler de tareas
│
├── resources/views/livewire/       ← Vistas Blade para cada módulo
├── database/migrations/            ← 23 migraciones
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
php artisan ssl:renew          # Forzar renovación de todos los SSL
php artisan tinker             # Consola interactiva de Laravel
```

---

## Seguridad

LaraPanel implementa múltiples capas de seguridad:

- **Autenticación** — Laravel Fortify con soporte para 2FA
- **Autorización** — Spatie Permissions con roles granulares
- **Comandos del sistema** — Lista blanca estricta de comandos permitidos en `SudoExecutor`
- **Anti path-traversal** — Validación estricta de rutas en `FileService` y `LogService`
- **API** — Tokens Sanctum con scopes. Los tokens se muestran una sola vez al crearlos
- **Cifrado** — Llaves privadas SSL cifradas en base de datos con el APP_KEY de Laravel

Para reportar vulnerabilidades, abre un issue privado

---

## Licencia

LaraPanel es software de código abierto publicado bajo la [Licencia MIT](LICENSE).

---

<div align="center">

Construido con ❤️ sobre [Laravel](https://laravel.com) y [Livewire](https://livewire.laravel.com)

</div>
