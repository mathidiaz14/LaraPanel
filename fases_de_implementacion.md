# LaraPanel — Fases de Implementación

> Análisis basado en `contexto_funcional.md` y `documentacion_arquitectura.md`.  
> Estado actual: **Alpha 0.1.0** — Núcleo funcional completo. Pendiente madurez, testing y módulos avanzados.

---

## Resumen de Estado Actual

| Categoría | Estado |
|-----------|--------|
| Infraestructura base (Laravel + Livewire + Shell) | ✅ Completo |
| Dashboard con métricas en tiempo real | ✅ Completo |
| Gestión de Dominios (Nginx/Apache) | ✅ Completo |
| SSL (Let's Encrypt, custom, self-signed) | ✅ Completo |
| Gestor de Archivos con editor Monaco | ✅ Completo |
| Email (cuentas, alias, DKIM, autoresponders) | ✅ Completo |
| Bases de datos MySQL | ✅ Completo |
| FTP | ✅ Completo |
| DNS con PowerDNS | ✅ Completo |
| Firewall UFW | ✅ Completo |
| Fail2ban | ✅ Completo |
| Antispam Rspamd | ✅ Completo |
| Antivirus ClamAV | ✅ Completo |
| Cron Jobs | ✅ Completo |
| Backups (archivos + BD) | ✅ Completo |
| Git Deploy con webhooks | ✅ Completo |
| Docker Manager | ✅ Completo |
| PHP Manager | ✅ Completo |
| Terminal Web | ✅ Completo |
| Logs del Sistema | ✅ Completo |
| WordPress Manager | ✅ Completo |
| Multi-Servidor SSH | ✅ Completo |
| Administración (usuarios, planes, tokens API) | ✅ Completo |
| Diseño Responsivo Móvil | ✅ Completo |

---

## FASE 1 — Estabilización y Seguridad
**Prioridad: CRÍTICA | Duración estimada: 2-3 semanas**

Esta fase es obligatoria antes de cualquier despliegue en producción real para usuarios externos. Consolida lo que ya está construido.

### 1.1 Autenticación de Dos Factores (2FA / TOTP)

**Por qué ahora:** El modelo `User` ya tiene `two_factor_enabled` y `two_factor_secret`. La infraestructura existe. Solo falta la UI y la lógica de verificación.

**Implementación:**
- Instalar `pragmarx/google2fa-laravel` o implementar con `OTPHP`.
- Generar QR code para escanear con Google Authenticator / Authy.
- Middleware que intercepte el login y pida el código TOTP si 2FA está activo.
- Vista de configuración 2FA en "Mi Perfil".
- Códigos de recupero de emergencia (guardar hash en BD).

**Archivos a crear/modificar:**
- `app/Http/Middleware/Require2FA.php` (nuevo)
- `resources/views/auth/two-factor.blade.php` (nuevo)
- `app/Livewire/Profile.php` (nuevo)

---

### 1.2 Página "Mi Perfil"

**Por qué ahora:** La ruta `/profile` existe pero devuelve `coming-soon`. Los usuarios necesitan cambiar su contraseña y configurar 2FA.

**Implementación:**
- Componente Livewire `Profile.php`.
- Cambiar nombre, email, contraseña.
- Activar/desactivar 2FA.
- Ver sesiones activas.
- Configurar timezone e idioma.

---

### 1.3 Tests Automatizados del Núcleo

**Por qué ahora:** Sin tests, cada cambio es un riesgo. La capa Shell es especialmente crítica.

**Implementación:**
- Tests unitarios para `ShellExecutor::validateCommand()` (whitelist).
- Tests unitarios para `FileService::resolvePath()` (path traversal).
- Tests de feature para webhooks Git (validación HMAC).
- Tests de feature para login y autenticación.
- Mocks de `SudoExecutor` en tests para no ejecutar comandos reales.

---

### 1.4 Rate Limiting en Webhooks y API

**Por qué ahora:** Los webhooks públicos pueden ser abusados.

**Implementación:**
- `throttle:60,1` en la ruta del webhook Git.
- Rate limiting en rutas de login (ya Laravel Fortify lo tiene, verificar configuración).
- Logging de intentos fallidos de autenticación webhook.

---

### 1.5 Validación Completa de Formularios

**Por qué ahora:** Algunos formularios tienen validación básica pero no exhaustiva.

**Implementación:**
- Revisar todos los `$rules` en los componentes Livewire.
- Agregar `unique` rules donde corresponda (ej: nombre de dominio ya existente).
- Mensajes de error en español consistentes.

---

## FASE 2 — Notificaciones y Monitoreo Avanzado
**Prioridad: ALTA | Duración estimada: 2 semanas**

### 2.1 Sistema de Notificaciones

**Por qué:** El sistema de alertas por umbral (CPU 90%, RAM 90%, Disco 85%) está configurado en `config/larapanel.php` pero no hay mecanismo de envío.

**Implementación:**
- Modelo `Notification` o uso del sistema nativo de Laravel.
- Canal **Email**: notificación via Laravel Mail cuando se supera un umbral.
- Canal **Telegram**: bot con token configurable en Settings.
- Panel de notificaciones en la topbar (el botón 🔔 ya existe pero no funciona).
- Tabla `notification_preferences` para que cada usuario configure qué alertas quiere.

**Componentes:**
- `app/Jobs/CheckSystemAlerts.php` (nuevo — ejecutar via scheduler cada 5 min)
- `app/Notifications/SystemAlertNotification.php` (nuevo)
- `app/Livewire/Admin/Settings.php` (modificar — agregar config Telegram)

---

### 2.2 Métricas Históricas (Gráficas)

**Por qué:** La tabla `server_metrics` existe pero no se usa para historial. El dashboard solo muestra datos en tiempo real.

**Implementación:**
- Job `app/Jobs/RecordServerMetrics.php` que guarda un snapshot cada 5 minutos en `server_metrics`.
- Limpieza automática de registros > 30 días (configurable).
- En el Dashboard: nuevas gráficas de historial 1h/24h/7d seleccionables.
- Chart.js con datos fetched via Livewire polling.

---

### 2.3 Monitoreo de Uptime de Sitios

**Por qué:** Los usuarios quieren saber si sus sitios están respondiendo.

**Implementación:**
- Job que hace HTTP GET a cada dominio activo cada 5 minutos.
- Registra `status_code`, `response_time_ms`, `is_up`.
- Badge de estado en la lista de dominios (verde/rojo).
- Historial de incidencias con duración del downtime.
- Notificación inmediata si un sitio cae.

---

## FASE 3 — Backups Avanzados
**Prioridad: ALTA | Duración estimada: 2 semanas**

### 3.1 Scheduler de Backups Automáticos

**Por qué:** El sistema de backups funciona manualmente. Los usuarios esperan backups automáticos.

**Implementación:**
- Modelo `BackupSchedule` con campos: `frequency` (daily/weekly/monthly), `time`, `domain_id`, `type`, `enabled`.
- Comando artisan `backup:run-scheduled` ejecutado por el scheduler de Laravel cada hora.
- UI para crear/editar/eliminar schedules en el módulo Backups.
- Notificación de éxito/fallo del backup automático.

---

### 3.2 Backups en la Nube (S3 / Backblaze / SFTP)

**Por qué:** `config/larapanel.php` ya tiene `backups.default_driver` con opciones `local|s3|sftp`. Solo falta implementarlo.

**Implementación:**
- Instalar `league/flysystem-aws-s3-v3` para S3/Backblaze.
- `league/flysystem-sftp-v3` para SFTP.
- UI en Settings para configurar credenciales del driver externo.
- `BackupService` usa el filesystem de Laravel para subir el `.tar.gz` al driver configurado.
- Retención configurable (eliminar backups > N días automáticamente).

---

### 3.3 Restauración de Backups desde UI

**Por qué:** Hacer un backup sin poder restaurarlo directamente desde la UI limita la utilidad del sistema.

**Implementación:**
- Botón "Restaurar" en el listado de backups.
- Modal de confirmación con advertencia de sobreescritura.
- `BackupService::restore()` que descomprime el `.tar.gz` al directorio destino.
- Para backups de BD: `mysql < dump.sql` via `SudoExecutor`.

---

## FASE 4 — Experiencia de Usuario y Productividad
**Prioridad: MEDIA | Duración estimada: 3 semanas**

### 4.1 Logs del Sistema en Tiempo Real

**Por qué:** El módulo de logs ya existe pero es estático (carga de una vez). Los devops esperan `tail -f` en tiempo real.

**Implementación:**
- Reutilizar el patrón de `$this->stream()` de Livewire ya implementado en el unzip.
- `ShellExecutor::runStreaming()` ya existe — conectar con el componente de Logs.
- Selector de archivo de log con botón "Seguir en vivo" que activa el streaming.
- Filtros de nivel (error, warn, info) aplicados client-side con Alpine.js.

---

### 4.2 phpMyAdmin / Gestor de BD Web

**Por qué:** Crear una BD desde el panel es útil, pero los usuarios también necesitan gestionar el contenido.

**Opción A (recomendada):** Integrar Adminer (single PHP file) como proxy reverso en Nginx.
**Opción B:** Iframe apuntando a una instancia de phpMyAdmin en subdominio protegido.

**Implementación (Opción A):**
- Descargar `adminer.php` al servidor.
- Configurar ruta Nginx con autenticación básica o token de sesión.
- Botón "Abrir Gestor" en el módulo de Bases de Datos que abre la URL con auto-login.

---

### 4.3 SSL Wildcard (DNS-01 Challenge)

**Por qué:** Los subdominios dinámicos requieren certificados wildcard (`*.dominio.com`).

**Implementación:**
- `SslService::issueWildcard()` usando `acme.sh --dns dns_pdns`.
- PowerDNS ya está integrado (DNS Manager), se puede usar para crear el registro `_acme-challenge` automáticamente.
- UI en `ssl/issue` con opción "Wildcard" que muestra los pasos.

---

### 4.4 Docker Compose desde UI

**Por qué:** Los usuarios avanzados trabajan con stacks de múltiples contenedores.

**Implementación:**
- Modelo `DockerStack` con `compose_yaml` (text) y `name`.
- `DockerService::composeUp()`, `composeDown()`, `composePull()`.
- Editor YAML embebido (Monaco ya está disponible en el proyecto).
- Listado de stacks con estado de cada servicio.

---

### 4.5 Marketplace de Scripts (Instaladores con 1-Click)

**Por qué:** Acelera enormemente la productividad del usuario.

**Implementación:**
- Modelo `ScriptTemplate` con `name`, `category`, `script_content`, `description`.
- Categorías: LAMP, LEMP, Node.js, Redis, WordPress, Laravel, etc.
- `ScriptService::run()` que ejecuta el template con variables sustituidas (dominio, PHP version, etc.).
- UI con grid de tarjetas de scripts disponibles.

---

## FASE 5 — Multi-Tenant (Resellers)
**Prioridad: MEDIA | Duración estimada: 4 semanas**

El flag `resellers => false` ya existe en `config/larapanel.php`. Esta fase lo activa completamente.

### 5.1 Modelo de Reseller

- Rol `reseller` ya existe en el modelo `User`.
- Un reseller tiene un `Plan` propio con cuotas totales.
- Los clientes del reseller consumen esas cuotas.
- El reseller tiene su propio panel de gestión de clientes.

### 5.2 Jerarquía de cuentas

```
Admin (LaraPanel owner)
  └── Reseller 1
        ├── Client A (plan: basic)
        ├── Client B (plan: pro)
        └── Client C (plan: basic)
  └── Reseller 2
        └── Client D
```

### 5.3 Implementación

- `User::reseller_id` campo FK que indica a qué reseller pertenece un cliente.
- Middleware que filtra los recursos del panel por `reseller_id`.
- Panel de reseller con: lista de clientes, uso de cuotas, crear/suspender clientes.
- Planes "de reseller" con cuotas máximas que el reseller puede distribuir.
- Branding personalizado: logo, nombre, colores del panel por reseller.

---

## FASE 6 — API REST Pública
**Prioridad: MEDIA | Duración estimada: 2 semanas**

### 6.1 Objetivos

Permitir gestión programática de LaraPanel desde scripts, CI/CD pipelines u otras aplicaciones.

### 6.2 Endpoints planificados

```
POST   /api/v1/domains              → Crear dominio
DELETE /api/v1/domains/{id}         → Eliminar dominio
GET    /api/v1/domains              → Listar dominios
POST   /api/v1/ssl/issue            → Emitir certificado SSL
POST   /api/v1/databases            → Crear base de datos
GET    /api/v1/monitoring/snapshot  → Métricas del servidor
POST   /api/v1/git/{id}/deploy      → Disparar deploy manual
GET    /api/v1/backups              → Listar backups
POST   /api/v1/backups              → Crear backup
```

### 6.3 Implementación

- Controladores en `app/Http/Controllers/Api/V1/`.
- Middleware `auth:sanctum` — los tokens API ya existen (módulo API Tokens).
- Rate limiting por token: 60 requests/minuto.
- Respuestas en JSON con estructura consistente: `{data, meta, errors}`.
- Documentación automática con Laravel Scribe o similar.

---

## FASE 7 — Soporte Multi-Distro y Madurez
**Prioridad: BAJA | Duración estimada: 4 semanas**

### 7.1 Soporte Debian / AlmaLinux / Rocky Linux

**Problema actual:** Los servicios asumen Ubuntu (paths, nombres de servicios, gestor de paquetes).

**Implementación:**
- Abstracción `OsAdapter` con implementaciones `UbuntuAdapter`, `DebianAdapter`, `AlmaLinuxAdapter`.
- Diferencias a abstraer: nombre de servicios (ej: `php8.3-fpm` vs `php-fpm`), gestor de paquetes (`apt` vs `dnf`), paths de configuración.
- Detección automática via `/etc/os-release` al instalar.

### 7.2 Soporte Apache Completo

- Los métodos `DomainService::deployApacheConfig()` existen pero no están completamente testeados.
- Agregar tests de integración con Apache.
- Soporte para `.htaccess` en el gestor de archivos (ya detectado como texto plano).

### 7.3 Internacionalización (i18n)

- Todos los textos están en español hardcodeado en las vistas.
- Migrar a `__('key')` con archivos de traducción `lang/es/*.php`.
- Agregar soporte para inglés como segundo idioma.
- Selector de idioma en perfil de usuario.

---

## FASE 8 — Integración Avanzada de CI/CD
**Prioridad: BAJA | Duración estimada: 2 semanas**

### 8.1 Pipelines de GitLab CI

- Actualmente solo se captura el webhook de `push` events.
- Extender `GitWebhookController` para capturar eventos de pipeline (`pipeline` event de GitLab).
- Mostrar estado del pipeline (running/passed/failed) en la UI de Git Deploy.

### 8.2 GitHub Actions Integration

- Soporte para el evento `workflow_run` además de `push`.
- Deploy solo si el workflow de CI pasó exitosamente.
- Mostrar badge de estado del último workflow en la UI.

---

## FASE 9 — Billing y Facturación
**Prioridad: BAJA / FUTURO | Duración estimada: 4-6 semanas**

Para cuando LaraPanel se ofrezca como SaaS o para resellers que cobran a sus clientes.

### 9.1 Componentes

- Integración con **Stripe** (recomendado) o PayPal.
- Modelo `Invoice` y `Subscription`.
- Ciclos de facturación mensuales/anuales.
- Portal del cliente para ver facturas y actualizar método de pago.
- Suspensión automática de cuenta por falta de pago.
- Uso de Laravel Cashier (ya compatible con Sanctum y el modelo User).

---

## Tabla de Prioridades Consolidada

| # | Feature | Fase | Prioridad | Esfuerzo |
|---|---------|------|-----------|----------|
| 1 | 2FA (TOTP) | 1 | 🔴 Crítica | 3 días |
| 2 | Página Mi Perfil | 1 | 🔴 Crítica | 2 días |
| 3 | Tests Automatizados | 1 | 🔴 Crítica | 5 días |
| 4 | Rate Limiting | 1 | 🔴 Crítica | 1 día |
| 5 | Notificaciones Email/Telegram | 2 | 🟠 Alta | 4 días |
| 6 | Métricas Históricas (gráficas) | 2 | 🟠 Alta | 3 días |
| 7 | Monitoreo de Uptime | 2 | 🟠 Alta | 3 días |
| 8 | Scheduler de Backups | 3 | 🟠 Alta | 3 días |
| 9 | Backups en S3/Backblaze | 3 | 🟠 Alta | 3 días |
| 10 | Restauración de Backups | 3 | 🟠 Alta | 2 días |
| 11 | Logs en Tiempo Real | 4 | 🟡 Media | 2 días |
| 12 | phpMyAdmin / Adminer | 4 | 🟡 Media | 2 días |
| 13 | SSL Wildcard (DNS-01) | 4 | 🟡 Media | 3 días |
| 14 | Docker Compose UI | 4 | 🟡 Media | 4 días |
| 15 | Marketplace de Scripts | 4 | 🟡 Media | 5 días |
| 16 | Sistema Resellers | 5 | 🟡 Media | 2 semanas |
| 17 | API REST Pública | 6 | 🟡 Media | 2 semanas |
| 18 | Soporte Debian/AlmaLinux | 7 | 🟢 Baja | 4 semanas |
| 19 | Soporte Apache completo | 7 | 🟢 Baja | 1 semana |
| 20 | Internacionalización (i18n) | 7 | 🟢 Baja | 1 semana |
| 21 | GitLab CI / GitHub Actions | 8 | 🟢 Baja | 2 semanas |
| 22 | Billing / Stripe | 9 | 🟢 Baja | 6 semanas |

---

## Decisiones Arquitectónicas Pendientes

Estas decisiones deben tomarse antes de comenzar las fases correspondientes:

| Decisión | Opciones | Impacto |
|----------|---------|---------|
| Driver de notificaciones | Email only vs Email+Telegram vs Email+Telegram+Webhook | Fase 2 |
| Storage de backups externos | S3 vs Backblaze B2 vs SFTP | Fase 3 |
| Gestor web de BD | Adminer (simple) vs phpMyAdmin (completo) | Fase 4 |
| i18n — idiomas objetivo | Solo EN/ES vs más idiomas | Fase 7 |
| Billing — procesador de pagos | Stripe vs PayPal vs MercadoPago | Fase 9 |
| TOTP — librería | pragmarx/google2fa vs OTPHP | Fase 1 |

---

*Documento generado el 22/06/2026 — LaraPanel v0.1.0-alpha*
