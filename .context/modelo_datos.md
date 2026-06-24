# Modelo de Datos (LaraPanel)

El sistema utiliza **Eloquent ORM** sobre una base de datos relacional. Existen 27 modelos mapeados, con una profunda integración para entornos multi-usuario y multi-servidor.

## Entidades Principales

### `User` (Administradores y Clientes)
- Maneja el control de acceso, estado 2FA, preferencias y asignación de planes.
- **Relaciones Clave:**
  - `BelongsTo -> Plan`: Límite de recursos asignados al cliente.
  - `HasMany -> Domain, DatabaseInstance, FtpAccount`: Recursos poseídos.
  - `HasMany -> AuditLog`: Trazabilidad de acciones del usuario.
  - `MorphToMany -> Spatie\Permission\Models\Role/Permission`: Autorización.

### `Domain` (Sitios Web y vHosts)
- Representa configuraciones activas de Nginx/Apache.
- Campos clave: `type` (main, subdomain, alias, parked), `document_root`, `php_version`, `status`.
- **Relaciones Clave:**
  - `BelongsTo -> User`.
  - `HasOne -> SslCertificate`: Metadatos del SSL instalado (Let's Encrypt o custom).
  - `HasMany -> EmailAccount, FtpAccount, Backup`.

### `Plan` y `Setting`
- `Plan` (Cuotas): Define máximos de dominios, bases de datos, emails, disco y flags de features permitidas (JSON).
- `Setting` (Configuración Global): Store Key-Value del panel.

## Infraestructura y Control

### `Server` y `ServerMetric` (Multi-Servidor)
- `Server`: Registra la IP, hostname, credenciales SSH (encriptadas) e info de OS del VPS esclavo.
- `ServerMetric`: Historico temporal (Time-Series data) de carga CPU, RAM, IO, para Chart.js.

### `DnsZone` y `DnsRecord` (PowerDNS)
- `DnsZone`: Dominios padres gestionados en PowerDNS.
- `DnsRecord`: Registros individuales (A, AAAA, TXT, MX, CNAME, etc).

### `GitDeployment` y `GitDeploymentLog` (Webhooks)
- `GitDeployment`: Mapeo de repositorio de GitHub/GitLab a un dominio, con un `webhook_secret` para firmar requests.
- `GitDeploymentLog`: Guarda la salida cruda de la consola del pipeline de deploy.

## Seguridad, Mantenimiento y Servicios Acoplados

### `AuditLog`
- Guarda información forense del sistema (`action`, `subject_type`, `meta JSON`, `ip_address`).

### `FirewallRule` y `Fail2banEvent`
- `FirewallRule`: Traduce reglas de UI a sentencias UFW subyacentes.
- `Fail2banEvent`: Log de IPs que han caído en Jails de fuerza bruta.

### Email, Antispam y Antivirus
- `EmailAccount`, `EmailAlias`, `EmailAutoresponder`: Tablas provistas para Postfix/Dovecot.
- `DkimKey`: Metadatos criptográficos por dominio.
- `SpamRule`: Gestión directa de Rspamd.
- `AntivirusScan`, `QuarantineFile`: Archivos aislados por ClamAV.

### Backups, Cron y Docker
- `Backup`: Referencia el tarball/sql.gz (físico o remoto).
- `CronJob` y `CronRunLog`: Expresiones cron registradas en el crontab del usuario del OS.
- `DockerContainer`: Caché del estado del socket de Docker (`/var/run/docker.sock`).
