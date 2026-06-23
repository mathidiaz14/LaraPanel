# LaraPanel — Contexto Funcional

> Panel de control de servidor web auto-hospedado, construido con **Laravel 11 + Livewire 4 + Alpine.js**.  
> Diseño Glassmorphism oscuro, completamente responsivo (desktop y móvil).

---

## Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8.3 / Laravel 11 |
| Frontend Reactivo | Livewire 4.x (SSR + streaming) |
| UI Interactiva | Alpine.js |
| Estilos | CSS puro (design system propio, variables CSS) |
| Base de Datos | MySQL / SQLite |
| Shell | Clase `SudoExecutor` (ejecuta comandos privilegiados de forma controlada) |
| Servidor Web | Nginx (configurable a Apache o ambos) |
| Autenticación | Laravel Auth + 2FA para admins |

---

## Arquitectura General

```
LaraPanel/
├── app/
│   ├── Livewire/        # Componentes reactivos (uno por módulo)
│   ├── Services/        # Lógica de negocio (23 servicios)
│   ├── Models/          # Modelos Eloquent (27 modelos)
│   ├── Shell/           # Capa de ejecución de comandos del sistema
│   │   ├── SudoExecutor.php         # Comandos locales con sudo
│   │   ├── RemoteShellExecutor.php  # Comandos SSH a servidores remotos
│   │   └── ServerContext.php        # Contexto local vs. remoto
│   ├── Http/Controllers/ # Webhooks, API tokens
│   └── Jobs/            # Colas de trabajo (backups, escaneos)
├── resources/views/livewire/  # Vistas Blade por módulo
├── public/css/larapanel.css   # Design system CSS global
├── config/larapanel.php       # Configuración central de módulos
└── routes/web.php             # Rutas protegidas + webhook git
```

### Patrón de Ejecución de Comandos

Toda interacción con el sistema operativo pasa por `SudoExecutor`, que:
1. Valida que el comando esté en la **whitelist** definida en `config/larapanel.php`.
2. Ejecuta con `sudo -n` (sin contraseña, via `/etc/sudoers.d/larapanel`).
3. Registra errores y lanza excepciones manejables.

En modo **multi-servidor**, los comandos van por `RemoteShellExecutor` vía SSH usando las credenciales almacenadas en el modelo `Server`.

---

## Módulos Desarrollados

### 1. Dashboard
**Archivo:** `app/Livewire/Dashboard.php`

Página de inicio con métricas en tiempo real del servidor:
- **CPU**: Uso %, desglose usuario/sistema/iowait, núcleos, modelo.
- **RAM**: Total, usada, libre, buffers, caché, swap.
- **Disco**: Todas las particiones (excluyendo tmpfs), con % de uso.
- **Red**: Velocidad de entrada/salida calculada entre dos snapshots.
- **Carga del sistema**: Load average 1m/5m/15m.
- **Uptime**: Tiempo de actividad del servidor.
- **Procesos Top**: Lista de los procesos que más CPU consumen.
- **Estado de Servicios**: Verifica si nginx, mysql, php-fpm, postfix, dovecot, fail2ban, pdns están activos.

**Lectura de datos:** `/proc/stat`, `/proc/meminfo`, `/proc/net/dev`, `/proc/loadavg`, `/proc/uptime`, `df`, `ps aux`.

---

### 2. Gestión de Dominios
**Servicio:** `DomainService.php`

Ciclo de vida completo de sitios web:
- **Crear dominio**: genera config virtual host Nginx/Apache, crea directorio raíz en `/var/www/{dominio}/public_html`, activa symlink en `sites-enabled`, recarga servidor web.
- **Tipos**: `main` (dominio principal), `subdomain`, `alias`, `parked`.
- **Versión de PHP por dominio**: configura el pool `php-fpm` correcto.
- **Suspender / Reactivar**: desactiva/activa el vhost sin borrarlo.
- **Eliminar**: elimina config, symlink y opcionalmente los archivos del servidor.
- **Registro de auditoría**: todas las acciones quedan en `AuditLog`.

---

### 3. SSL / TLS
**Servicio:** `SslService.php`

Tres métodos de certificado:
1. **Let's Encrypt** via `acme.sh` (sin downtime, challenge por webroot). Incluye soporte para `www.` como SAN automático.
2. **Certificado Personalizado**: instalación de certificado externo (crt + key + chain).
3. **Autofirmado** (`openssl`): para uso interno/desarrollo.

Funciones adicionales:
- Detección de expiración próxima (badge de alerta en dominio).
- Auto-renovación mediante cron de `acme.sh`.
- Reconfiguración automática del vhost para HTTPS + redirección 301.

---

### 4. Gestor de Archivos
**Servicio:** `FileService.php` | **Componente:** `Files/FileManager.php`

Explorador de archivos tipo panel de hosting:
- **Exploración**: listar directorios con tamaño, permisos, propietario, fecha de modificación.
- **Navegación**: árbol lateral con accesos rápidos (raíz, HTML público, sitios web).
- **Edición en línea**: editor Monaco (VS Code en el navegador) con highlight por extensión. Guardado con `Ctrl+S`.
- **Subida de archivos**: modal con barra de progreso en tiempo real vía Livewire. Soporta hasta 2 GB. Mueve el archivo temporal al destino con `SudoExecutor`.
- **Descompresión en tiempo real**: modal con barra de progreso y log de archivos extraídos uno por uno usando `unzipStream` + `$this->stream()` de Livewire.
- **Compresión**: empaqueta carpetas o selección de archivos en `.zip`.
- **Permisos (chmod)**: modal para cambiar permisos con selector octal.
- **Operaciones**: renombrar, mover, copiar, eliminar, crear carpeta/archivo, descargar.
- **Seguridad**: `resolvePath()` previene directory traversal. Todos los paths se validan contra el root `/var/www`.
- **Apertura inteligente**: archivos sin extensión o desconocidos abren diálogo de confirmación. Doble clic en nombre abre editor directamente.

---

### 5. Email
**Servicios:** `EmailService.php`, `DkimService.php`

Gestión completa de correo (Postfix + Dovecot):
- **Cuentas de email**: crear, eliminar, cambiar contraseña. Crea buzón en `/var/mail/vhosts/{dominio}/{usuario}`.
- **Alias**: redirección de direcciones a otras cuentas.
- **Autoresponders**: respuestas automáticas con fecha de inicio/fin.
- **Estadísticas de email**: emails enviados, recibidos, rechazados (lectura de logs de Postfix).
- **DKIM**: generación de claves RSA 2048 con `openssl`, guardado en `/etc/rspamd/dkim/`, registro en PowerDNS.

---

### 6. Bases de Datos
**Servicio:** `DatabaseService.php`

Gestión de MySQL:
- Crear base de datos con charset/collation configurable.
- Crear usuario MySQL asociado con contraseña aleatoria.
- Asignar permisos (`GRANT ALL`).
- Eliminar base de datos y usuario.
- Listado con tamaño de cada BD.

---

### 7. FTP
**Servicio:** `FtpService.php`

Gestión de cuentas FTP (vsftpd/ProFTPD):
- Crear usuario FTP con directorio raíz y contraseña.
- Eliminar usuario.
- Listar cuentas activas.

---

### 8. DNS Manager
**Servicio:** `DnsService.php`

Integración con PowerDNS vía API REST:
- Crear/eliminar zonas DNS.
- Gestionar registros: A, AAAA, CNAME, MX, TXT, NS, SRV, CAA.
- Editor visual de zona con tabla de registros.
- Propagación automática al agregar dominios.

---

### 9. Firewall
**Servicio:** `FirewallService.php`

Gestión de UFW (Uncomplicated Firewall):
- Ver estado y reglas activas.
- Habilitar / Deshabilitar UFW.
- Agregar reglas personalizadas (puerto, protocolo, IP origen, dirección, acción).
- **Presets de un click**: SSH, HTTP, HTTPS, SMTP, IMAP, POP3, MySQL (bloqueado), Redis (bloqueado), etc.
- Activar/Desactivar reglas individuales sin eliminarlas.
- Estadísticas de conexiones activas (via `ss -s`).
- Protección anti-lockout: siempre mantiene SSH puerto 22 permitido.

---

### 10. Fail2ban
**Servicio:** `Fail2banService.php`

Protección contra fuerza bruta:
- Ver estado global de Fail2ban.
- Listar "jails" activas y IPs baneadas.
- Desbanear IPs manualmente.
- Ver eventos recientes de baneo.

---

### 11. Antispam (Rspamd)
**Servicio:** `AntispamService.php`

Integración con Rspamd:
- Ver estadísticas de spam (emails analizados, rechazados, calificaciones).
- Gestionar reglas de spam personalizadas.
- Ver y editar lista blanca/negra de IPs y dominios.

---

### 12. Antivirus (ClamAV)
**Servicio:** `AntivirusService.php`

Escaneo de malware con ClamAV:
- Verificar instalación y versión de ClamAV.
- Ver fecha de definiciones de virus.
- **Escaneo recursivo** de una ruta con `clamscan --recursive`.
- Opción de **cuarentena automática** (mueve archivos infectados a `/var/larapanel/quarantine`).
- Gestión de cuarentena: ver archivos, restaurar o eliminar definitivamente.
- Actualizar definiciones con `freshclam`.
- Historial de escaneos con resultados.

---

### 13. Cron Jobs
**Servicio:** `CronService.php`

Gestión de tareas programadas:
- Crear/editar/eliminar cron jobs con expresión cron visual (minuto, hora, día, mes, día semana).
- Ejecutar manualmente un job al instante.
- Historial de ejecuciones con output y estado (success/failed).
- Escribe los cambios en el crontab del sistema.

---

### 14. Backups
**Servicio:** `BackupService.php`

Sistema de copias de seguridad:
- **Tipos**: Solo archivos, solo base de datos, o completo (ambos).
- **Scope**: un dominio específico o todos los dominios del usuario.
- Genera archivos `.tar.gz` y `.sql.gz`.
- Descarga del backup desde el navegador.
- Eliminación con limpieza de archivo en disco.
- Listado de backups con tamaño y fecha.
- **Futuro**: drivers para S3/SFTP (ya configurado en `config/larapanel.php`).

---

### 15. Git Deploy
**Servicio:** `GitService.php` | **Componente:** `Git/GitIndex.php`

Integración CI/CD con GitHub/GitLab:
- Configurar repositorios enlazados a un dominio.
- **Auto-Deploy vía Webhook**: URL única por repositorio, validación de firma HMAC (`X-Hub-Signature-256`).
- **Despliegue Manual**: botón "Desplegar Ahora" desde la UI.
- Flujo: `git fetch` + `git reset --hard origin/{branch}` (o `git clone` si es primera vez).
- Script de post-deploy personalizado (bash) ejecutado en la raíz del proyecto.
- **Estado del repositorio en tiempo real**: rama actual, último commit descargado, archivos modificados localmente (detecta conflictos potenciales).
- Historial de despliegues con estado (success/failed), commit hash, mensaje y log completo.
- Indicador visual de Auto-Deploy activo/inactivo.

---

### 16. Docker
**Servicio:** `DockerService.php`

Gestión de contenedores Docker:
- Listar contenedores (corriendo y detenidos), agrupados por prefijo/sufijo del nombre.
- Iniciar, detener, reiniciar, eliminar contenedores.
- Ver logs de un contenedor (últimas N líneas).
- Estadísticas en vivo (CPU %, memoria, red, I/O de bloque).
- Inspeccionar detalles completos (`docker inspect`).
- Listar imágenes locales.
- Pull de nuevas imágenes.
- Eliminar imágenes no usadas.

---

### 17. PHP Manager
**Servicio:** `PhpService.php`

Gestión de versiones de PHP:
- Ver versiones instaladas en el servidor.
- Cambiar la versión de PHP de un dominio (reconfigura el pool php-fpm y el vhost Nginx).
- Ver extensiones activas.
- Modificar `php.ini` por versión (límites de memoria, upload, timeout).

---

### 18. Terminal Web
**Servicio:** `TerminalService.php`

Terminal integrada en el navegador (solo admins):
- Ejecuta comandos en el servidor desde la UI.
- Historial de comandos en la sesión.
- Output en tiempo real.
- Comandos bloqueados por la whitelist de seguridad.

---

### 19. Logs del Sistema
**Servicio:** `LogService.php`

Visor de logs del servidor:
- Lectura de logs de Nginx, Apache, PHP, MySQL, Postfix, auth.log.
- Filtrado por nivel (error, warn, info).
- Búsqueda por texto.
- Paginación por cola (`tail -n`).
- Limpieza/truncamiento de logs.

---

### 20. Monitoreo de Recursos (Dashboard Avanzado)
**Servicio:** `MonitoringService.php`

Métricas históricas y en tiempo real:
- Gráficas de CPU, RAM, disco y red (Chart.js).
- Polling cada 5 segundos (configurable en `config/larapanel.php`).
- Alertas configurables: umbral CPU (90%), RAM (90%), Disco (85%).
- Soporte para servidores remotos vía `RemoteShellExecutor`.

---

### 21. WordPress Manager
**Servicio:** `WordPressService.php`

Detección y gestión de instalaciones WordPress:
- Detectar automáticamente instalaciones de WP en los dominios.
- Ver versión de WP y plugins instalados.
- Actualizar WP core, plugins y temas vía WP-CLI.

---

### 22. Administración (Solo Admin)
**Componentes:** `Admin/UserIndex`, `Admin/PlanIndex`, `Admin/Settings`, `Admin/ApiTokens`

- **Gestión de Usuarios**: crear, editar, suspender, eliminar usuarios. Asignar planes.
- **Planes de Hosting**: definir límites por plan (dominios, almacenamiento, bases de datos, emails, etc.).
- **Configuración Global**: nombre del panel, dominio, configuraciones de email del sistema.
- **Tokens API**: generar y revocar tokens para acceso programático.
- **Sistema de actualizaciones**: detecta nuevas versiones de LaraPanel (badge animado en sidebar).

---

### 23. Multi-Servidor
**Servicio:** `ServerService.php` | **Componente:** `Servers/`

Gestión de múltiples servidores remotos:
- Registrar servidores con IP, puerto SSH, usuario y clave privada.
- Selector de servidor en la topbar: cambia el contexto global de todos los módulos.
- `ServerContext`: patrón singleton que determina si se está en modo local o remoto.
- Ping periódico para verificar disponibilidad y obtener métricas básicas del servidor remoto.
- Las métricas del dashboard se leen del servidor activo.

---

## Seguridad

| Mecanismo | Descripción |
|-----------|-------------|
| Whitelist de comandos | Solo comandos explícitamente autorizados en `config/larapanel.php` pueden ejecutarse con sudo |
| Path traversal prevention | `FileService::resolvePath()` valida que toda ruta esté bajo `/var/www` |
| CSRF | Protección nativa de Laravel en todos los formularios |
| 2FA para admins | Configurable en `config/larapanel.php` |
| Audit Log | Todas las acciones destructivas quedan registradas en la tabla `audit_logs` |
| Webhook HMAC | Los webhooks de Git validan la firma SHA-256 antes de ejecutar el deploy |
| Session timeout | 60 minutos de inactividad (configurable) |
| Max login attempts | 5 intentos antes de bloqueo temporal |

---

## Modelos de Base de Datos

| Modelo | Descripción |
|--------|-------------|
| `User` | Usuarios del panel con roles (admin/user) |
| `Domain` | Dominios/subdominios con config de PHP y webserver |
| `SslCertificate` | Certificados SSL con proveedor, fechas y estado |
| `EmailAccount` | Cuentas de correo electrónico |
| `EmailAlias` | Alias de email |
| `EmailAutoresponder` | Respuestas automáticas de email |
| `DkimKey` | Claves DKIM por dominio |
| `DatabaseInstance` | Bases de datos MySQL |
| `FtpAccount` | Cuentas FTP |
| `DnsZone` | Zonas DNS de PowerDNS |
| `DnsRecord` | Registros DNS individuales |
| `FirewallRule` | Reglas de UFW |
| `Fail2banEvent` | Eventos de baneo registrados |
| `SpamRule` | Reglas de antispam personalizadas |
| `CronJob` | Tareas cron configuradas |
| `CronRunLog` | Historial de ejecuciones de cron |
| `Backup` | Registro de backups con metadata |
| `GitDeployment` | Configuraciones de repositorios Git |
| `GitDeploymentLog` | Historial de despliegues Git |
| `DockerContainer` | Cache de contenedores Docker |
| `AntivirusScan` | Historial de escaneos ClamAV |
| `QuarantineFile` | Archivos en cuarentena |
| `Server` | Servidores remotos registrados |
| `ServerMetric` | Métricas históricas de servidores |
| `Plan` | Planes de hosting con límites |
| `Setting` | Configuración global del panel |
| `AuditLog` | Log de auditoría de todas las acciones |

---

## Funcionalidades Futuras (Hoja de Ruta)

### 🔴 Alta Prioridad

| Funcionalidad | Descripción |
|---------------|-------------|
| **2FA (TOTP)** | Autenticación de dos factores con Google Authenticator para todos los usuarios |
| **Backups a S3/Backblaze** | Envío automático de backups a almacenamiento en la nube (driver ya preparado en config) |
| **Notificaciones** | Sistema de alertas por email/Telegram cuando CPU/RAM/disco supera umbrales |
| **Scheduler Backups** | Programar backups automáticos recurrentes (diarios, semanales) |
| **Restauración de Backups** | Restaurar desde un backup directamente desde la UI |

### 🟡 Media Prioridad

| Funcionalidad | Descripción |
|---------------|-------------|
| **Resellers** | Sistema multi-tenant para revendedores (ya existe flag `resellers => false` en config) |
| **Webmail integrado** | Acceso a Roundcube/Rainloop desde el panel |
| **phpMyAdmin embebido** | Acceso directo a la BD desde la UI (iframe o proxy) |
| **WP-CLI completo** | Comandos WP-CLI interactivos desde la UI (actualmente solo detección) |
| **Monitoreo de uptime** | Verificar cada N minutos si los sitios responden (200 OK) con historial |
| **SSL Wildcard** | Soporte para certificados wildcard con desafío DNS-01 |
| **Logs en tiempo real** | Tail -f de logs con streaming Livewire (igual al unzip) |
| **Editor DNS avanzado** | Importar/exportar zona en formato BIND |
| **API REST pública** | Endpoints REST autenticados por token para gestión programática |
| **Métricas históricas** | Gráficas de CPU/RAM/disco con datos de las últimas 24h/7d/30d |

### 🟢 Largo Plazo

| Funcionalidad | Descripción |
|---------------|-------------|
| **Soporte Debian/AlmaLinux** | Actualmente optimizado para Ubuntu. Adaptar scripts para otros distros |
| **Soporte Apache** | Los métodos Apache ya existen en `DomainService` pero no están 100% testeados |
| **Docker Compose UI** | Crear y gestionar stacks Docker Compose desde la interfaz |
| **Let's Encrypt Wildcard** | Implementar challenge DNS-01 con PowerDNS para certificados wildcard |
| **Marketplace de scripts** | Instaladores con un click (LAMP, Node.js, Redis, MongoDB, etc.) |
| **Integración GitLab CI** | No solo webhook de push, sino integración con pipelines de GitLab |
| **Autoscaling remoto** | Agregar/quitar VPS dinámicamente en el modo multi-servidor |
| **Panel de facturación** | Módulo de billing para hosters (integración Stripe/PayPal) |

---

## Scripts de Instalación

El proyecto incluye scripts bash para instalación en servidores limpios:

| Script | Propósito |
|--------|-----------|
| `install.sh` | Instalación completa del entorno (Nginx, PHP, MySQL, etc.) |
| `install-dns.sh` | Instalación y configuración de PowerDNS |
| `install-mailserver.sh` | Instalación de Postfix + Dovecot + Rspamd |
| `install-webmail.sh` | Instalación de cliente webmail |
| `install-autologin.sh` | Configuración de sudo sin contraseña para LaraPanel |
| `update.sh` | Script de actualización de LaraPanel en producción |

---

## Flujo de Datos Típico

```
Usuario hace clic en la UI
    → Livewire Component (PHP en servidor)
        → Service (lógica de negocio)
            → SudoExecutor / Shell (si necesita privilegios)
                → Sistema Operativo / Nginx / MySQL / etc.
            → Model Eloquent (persistencia en BD)
        → Actualiza propiedades Livewire
    → Alpine.js aplica transiciones en el DOM
← Respuesta al navegador (diff de DOM via WebSocket/HTTP)
```

---

*Generado automáticamente el {{ date }} | LaraPanel v{{ version }}*
