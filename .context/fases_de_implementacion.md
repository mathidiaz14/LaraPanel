# Fases de Implementación (LaraPanel)

Esta hoja de ruta está basada íntegramente en la documentación maestra del repositorio, auditada bajo el estado actual del código (v0.1.0-alpha).

## Fase 0: Estado Inicial / Base Existente
*Estado: Completada*
El núcleo del proyecto, su framework estructural, diseño UI y todos los conectores con los servicios primarios del OS (Nginx, PHP, MySQL, Postfix, Docker, PowerDNS) están consolidados en 23 Servicios de Laravel y 27 Modelos Eloquent interactivos.

## Fase 1: Estabilización y Seguridad Core
*Estado: Completada*
**Prioridad:** CRÍTICA.
- [x] **1.1. Autenticación de Dos Factores (2FA):** Integración nativa de Laravel Fortify (TOTP).
- [x] **1.2. Interfaz de Perfil de Usuario:** Creación del componente completo para password y gestión de sesiones.
- [x] **1.3. Pruebas Automatizadas (PHPUnit):** Suite de Unit/Feature tests para `ShellExecutor` (whitelist) y prevenciones de Path Traversal.
- [x] **1.4. Rate Limiting en API y Webhooks:**
  - Límite global en rutas críticas (`throttle:60,1` en webhooks, protección del login frente a brute-force).
- [x] **1.5. Validación Exhaustiva de Formularios:**
  - Repaso de `$rules` en los componentes Livewire previniendo inyecciones o desbordes en el Bash subyacente.

## Fase 2: Notificaciones y Monitoreo Histórico
*Estado: Completada*
**Prioridad:** ALTA.
- [x] **2.1. Arquitectura de Canales (Notifications):** Disparo de alertas usando Laravel Notifications via Email y paquete de Telegram.
- [x] **2.2. Monitoreo de Umbrales (Alarms):** Script periódico (`CollectServerMetricsCommand`) evaluando `ServerMetric` para CPU/RAM, y `CheckDomainsUptimeCommand` para caídas.
- [x] **2.3. Métricas Históricas & Uptime:** Volcado histórico persistente visualizado en Chart.js (1h/24h/7d) e iteración contra dominios vivos.

## Fase 3: Backups Avanzados (Nube)
*Estado: Completada*
**Prioridad:** ALTA.
- [x] **3.1. Scheduler de Backups:** Programación recurrente visual vinculada al cron maestro.
- [x] **3.2. S3/Backblaze / SFTP:** Configuración de `league/flysystem-aws-s3-v3` para persistencia remota de los tarballs de sitios web y bases de datos.
- [x] **3.3. Restauración 1-Click:** Lógica inversa al `BackupService` para restaurar dumps desde la propia UI.

## Fase 4: Experiencia UI Devops
*Estado: Completada*
**Prioridad:** MEDIA.
- [x] **4.1. Logs del Sistema Paginados/Live:** Visor de logs paginado con filtrado interactivo e integrado (existente).
- [x] **4.2. Gestor Web de Base de Datos:** Embeber Adminer de forma segura bajo autenticación de Laravel con bypass de CSRF.
- [x] **4.3. SSL Wildcard (DNS-01):** Integración con `acme.sh` y variables de entorno de PowerDNS local para resolver certificados comodín `*.domain.com`.
- [x] **4.4. Docker Compose UI / Scripts Marketplace:** Catálogo interactivo de templates (WordPress, Postgres, Redis, Node.js) con un solo clic.

## Fase 5: Multi-Tenant y Resellers
*Estado: Pendiente*
**Prioridad:** MEDIA.
Separación jerárquica: El admin global tiene acceso a shell y panel de Nginx, pero los clientes solo acceden a sus porciones controladas por un Plan (cuotas). Los `Resellers` pueden administrar cuotas hijas en bloque.

## Fase 6: API REST Pública
*Estado: Pendiente*
**Prioridad:** MEDIA.
Exposición de controladores (`/api/v1/`) protegidos por `auth:sanctum` para orquestar la infraestructura externamente.

## Fase 7, 8 y 9: Escalabilidad y Facturación
*Estado: Baja prioridad*
- Adaptadores multiplataforma (Debian/AlmaLinux).
- Integración profunda CI/CD (GitLab Pipelines).
- Modulo de cobro recurrente (Billing con Stripe).

## Fase 10: Performance, WAF y CDN Propio (Estilo Cloudflare)
*Estado: Completada (bugs corregidos 2026-08-10)*
**Prioridad:** MEDIA-ALTA.
Infraestructura completa (migración `domain_performance_settings`, modelo `DomainPerformanceSetting`, `GeoWafService`, `GoAccessService`, Job `GenerateGoAccessReport`, componente `PerformanceIndex` con sus 6 pestañas y ruta `/performance` admin-only).

- [x] **10.1. Under Attack Mode:** Toggle que inyecta limitadores en el vhost. ✅ Corregido: `limit_req_zone`/`limit_conn_zone` movidas al contexto `http` (encima del `server {}`) vía `DomainService::buildPerformanceZones()`; dentro del server solo `limit_req`/`limit_conn`.
- [x] **10.2. FastCGI Microcaching:** Botón UI para cachear salida dinámica con modo "Purge Dev". ✅ (`fastcgi_cache_path` sobre el server block). Nombres de zona saneados con `zoneToken()`.
- [x] **10.3. Geo-WAF (MaxMind):** Módulo Nginx para bloquear visitas por ISO Code. ✅ Corregido: `geoip2 {}` y `map {}` al contexto `http`; modo *allow* arreglado (map `default 1`, países listados `0`, `if ($blocked_country) return 403` bloquea solo no-listados).
- [x] **10.4. Proxy DNS Inverso (Orange Cloud):** Proxy frontal para backends/containers. ✅ (`proxy_pass` con `proxy_websocket`).
- [x] **10.5. GoAccess Analytics:** Estadísticas desde logs de Nginx vía job en cola. ✅
- [x] **10.6. Page Rules y SSL Avanzado:** Headers de seguridad (HSTS) y redirecciones 301/302. ✅ Corregido: HSTS solo se emite con `hsts_enabled=true` (`buildHstsHeader` devuelve `''` si off); headers custom saneados (bloqueo de `\n`/`\r`, nombre validado `[A-Za-z0-9-]`); `brotli_enabled` renderizado (`brotli on;` requiere módulo `ngx_http_brotli`).

Nota: los nombres de zona/variable de Nginx se sanean reemplazando puntos por `_` (`zoneToken()` → `cache_example_com`, `blocked_country_example_com`) para evitar configs inválidas con dominios `*.com`.

Verificación: `php -l` OK, suite 20/21 (la única falla es `FileServiceTest` por path traversal en Windows, fase 11.3.1), salida real de `generateNginxConfig`/`generateNginxSslConfig` inspeccionada con features activados.

## Fase 11: Correcciones de Seguridad y Deuda Técnica (Auditoría)

*Estado: Pendiente*
**Prioridad:** CRÍTICA.

Backlog de hallazgos identificados en la auditoría del 2026-08-10. Agrupados por severidad, con referencia `archivo:línea` para trazabilidad.

### 11.1 CRÍTICO — Seguridad (RCE / Escalada de Privilegios)

- [x] **11.1.1. IDOR en API de cuentas:** `routes/api.php` (grupo `auth:sanctum`, ~línea 11) permite a cualquier cliente suspennder/terminar a cualquier usuario mediante `User::findOrFail($id)` (`app/Http/Controllers/Api/AccountController.php:55-111`). Corregir añadiendo `->middleware('role:admin')` (o `Gate::authorize`), validación de propiedad y `throttle`. ✅ **Corregido (2026-08-10):** grupo `v1/accounts` ahora con `['auth:sanctum', 'role:admin', 'throttle:60,1']`; `manageableAccount()` impide gestionar la propia cuenta y administradores (422); `AuditLog` en create/suspend/unsuspend/terminate; `plan?->name` y `max:72` en password. Test: `tests/Feature/AccountApiTest`.
- [x] **11.1.2. RCE vía instalación WordPress:** `routes/web.php:112` expone `/wordpress` solo bajo `auth` (sin `role:admin`) y `app/Services/WordPressService.php:46` interpola `$title`/`$url`/`$adminEmail` crudos en un string de shell (`wp core install ... --title="$(cmd)"`). Escapar con `escapeshellarg` y proteger la ruta por rol. ✅ **Corregido (2026-08-10):** `/wordpress` movida al grupo `role:admin`; todos los args del CLI `wp` escapados con `escapeshellarg`; validación reforzada (`siteTitle` sin caracteres de control, `adminPass` max 72, `adminEmail` max 254).
- [x] **11.1.3. Terminal web fuera de la whitelist:** `app/Services/TerminalService.php:49-72` ejecuta comandos arbitrarios con la facade `Process` y auto-eleva con `sudo -n`, ignorando `config/larapanel.security.allowed_sudo_commands`. Encaminar por `SudoExecutor`/whitelist o limitar binarios permitidos en el admin. ✅ **Corregido (2026-08-10):** nueva whitelist `security.allowed_terminal_commands`; se rechazan separadores shell (`; | && || \` $() \n`) y cualquier binario fuera de la lista; `cd` escapa la ruta con `escapeshellarg`.
- [x] **11.1.4. Whitelist desactivada en entornos no-producción:** `app/Shell/ShellExecutor.php:151-154` (`if (!app()->isProduction()) return;`) permite ejecutar cualquier binario en dev/preview/demo. La whitelist debe aplicarse siempre, prescindiendo de `APP_ENV`. ✅ **Corregido (2026-08-10):** eliminado el early-return; `validateCommand()` aplica siempre la whitelist (verificado en dev vía tinker: `evilbin` bloqueado). `ShellExecutorTest` ya forzaba prod, sin cambios; suite completa 25/26 (solo falla preexistente `FileServiceTest` Windows).

### 11.2 ALTO — Seguridad adicional

- [x] **11.2.1. Shell crudo prohibido (`shell_exec`/`exec`/`popen`):** Migrar a `ShellExecutor`/`SudoExecutor`. ✅
- [x] **11.2.2. `Process::run()` con strings (bypass whitelist/auditoría):** Usar arrays tipados + `escapeshellarg`. ✅
- [x] **11.2.3. Binarios usados vía sudo sin estar whitelisteados:** Whitelist actualizada en `config/larapanel.php`. ✅
- [x] **11.2.4. Secretos por defecto hardcodeados / en claro:** Uso de `Setting::setSecret` con cifrado en reposo. ✅
- [x] **11.2.5. Token de auto-login webmail world-readable:** Mover de `/tmp` a espacio privado con permisos restrictivos. ✅
- [x] **11.2.6. Secreto de webhook en la URL:** Eliminado uso de query param `?secret=...`. ✅
- [x] **11.2.7. Inyección de líneas en crontab:** Validación estricta de regex en `CronService`. ✅

### 11.3 MEDIO — Bugs funcionales

- [x] **11.3.1. Path traversal roto en Windows:** `app/Services/FileService.php` normaliza separadores `/` e impide escapes a directorios hermanos con la barra final. Pruebas unitarias corregidas y pasando 4/4 (`tests/Unit/FileServiceTest.php`). ✅
- [x] **11.3.2. `CheckDomainsUptimeCommand` agendado:** Agregada la tarea programada `panel:check-uptime` cada 5 minutos en `routes/console.php` con salida a `logs/domains-uptime.log`. ✅
- [x] **11.3.3. Claves de config corregidas en metrics:** `CollectServerMetricsCommand.php` actualizado para leer `larapanel.monitoring.alerts.cpu_threshold` y `ram_threshold`. ✅
- [x] **11.3.4. `RemoteShellExecutor` completado:** Agregado `withEnv()`, validación contra la whitelist `allowed_sudo_commands`, captura de `stderr` con `$ssh->getStdError()` y manejo de código de salida (`codeInt`). Pruebas unitarias integradas (3/3 pasando en `tests/Unit/RemoteShellExecutorTest.php`). ✅
- [x] **11.3.5. Config `server` consolidada:** Unificada la clave `'public_ip'` en el bloque principal y eliminado el bloque duplicado en `config/larapanel.php`. ✅

### 11.4 MEDIO — Multi-tenant y Middleware

- [ ] **11.4.1. 2FA de admin sin enforce:** `larapanel.security.2fa_required_for_admin` nunca se lee; no existe middleware `Require2FA`.
- [ ] **11.4.2. Migraciones con FKs/indexes faltantes:** `users.plan_id` y `audit_logs.user_id` sin FK; `uptime_monitors.server_id` string; `docker_containers.name` unique global (bloquea Phase 5); `server_metrics` sin `server_id`.
- [ ] **11.4.3. Impersonación por GET sin CSRF ni AuditLog:** `app/Http/Controllers/Admin/ImpersonationController.php:38` (`auth()->login()` vía GET).
- [ ] **11.4.4. SSO phpMyAdmin con credenciales en disco:** `routes/web.php:140-155` escribe `DB_USERNAME:DB_PASSWORD` (vía `env()`) en `/tmp` sin expiración ni limpieza del token.
- [ ] **11.4.5. Roles dobles sin poblar:** Spatie `HasRoles` + columna `role` string; nadie asigna roles Spatie, por lo que `User::role('admin')` (`CollectServerMetricsCommand:87`) devuelve vacío.

### 11.5 BAJO — Frontend (Visual Standards)

- [x] **11.5.1. Sustituir `class="table"` por `class="lp-table"`:** `admin/api-tokens.blade.php`, `admin/plan-index.blade.php`, `admin/user-index.blade.php`, `files/file-manager.blade.php`, `email/email-stats.blade.php`. ✅
- [x] **11.5.2. Reemplazar grids fijos `repeat(4/5,1fr)` por `.stats-row`/`.lp-two-col`/`.lp-three-col`:** `fail2ban-index.blade.php`, `admin/plan-index.blade.php`, `antivirus-index.blade.php`, `antispam-index.blade.php`, `domains/domain-create.blade.php`, `servers/servers-index.blade.php`. ✅
- [x] **11.5.3. Eliminar hex hardcodeados y `style=` inline** a favor de tokens CSS (`var(--success)`, `var(--info)`, etc.): `servers-index`, `git-index`, `file-manager`, `tree-node`, `wordpress-index`. ✅
