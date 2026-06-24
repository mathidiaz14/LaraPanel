# Fases de Implementación (LaraPanel)

Esta hoja de ruta está basada íntegramente en la documentación maestra del repositorio, auditada bajo el estado actual del código (v0.1.0-alpha).

## Fase 0: Estado Inicial / Base Existente
*Estado: Completada*
El núcleo del proyecto, su framework estructural, diseño UI y todos los conectores con los servicios primarios del OS (Nginx, PHP, MySQL, Postfix, Docker, PowerDNS) están consolidados en 23 Servicios de Laravel y 27 Modelos Eloquent interactivos.

## Fase 1: Estabilización y Seguridad Core
*Estado: En progreso avanzado*
**Prioridad:** CRÍTICA.
- [x] **1.1. Autenticación de Dos Factores (2FA):** Integración nativa de Laravel Fortify (TOTP).
- [x] **1.2. Interfaz de Perfil de Usuario:** Creación del componente completo para password y gestión de sesiones.
- [x] **1.3. Pruebas Automatizadas (PHPUnit):** Suite de Unit/Feature tests para `ShellExecutor` (whitelist) y prevenciones de Path Traversal.
- [ ] **1.4. Rate Limiting en API y Webhooks:**
  - Límite global en rutas críticas (`throttle:60,1` en webhooks, protección del login frente a brute-force).
- [ ] **1.5. Validación Exhaustiva de Formularios:**
  - Repaso de `$rules` en los componentes Livewire previniendo inyecciones o desbordes en el Bash subyacente.

## Fase 2: Notificaciones y Monitoreo Histórico
*Estado: Pendiente*
**Prioridad:** ALTA.
- [ ] **2.1. Arquitectura de Canales (Notifications):** Disparo de alertas usando Laravel Notifications via Email y bot de Telegram.
- [ ] **2.2. Monitoreo de Umbrales (Alarms):** Script periódico evaluando `ServerMetric`. Alerta al administrador si (CPU > 90% o RAM > 95%).
- [ ] **2.3. Métricas Históricas & Uptime:** Volcado histórico persistente en `server_metrics` visualizado en Chart.js (1h/24h/7d) e iteración contra dominios vivos para medir caídas (status_code 200).

## Fase 3: Backups Avanzados (Nube)
*Estado: Pendiente*
**Prioridad:** ALTA.
- [ ] **3.1. Scheduler de Backups:** Programación recurrente visual vinculada al cron maestro.
- [ ] **3.2. S3/Backblaze / SFTP:** Configuración de `league/flysystem-aws-s3-v3` para persistencia remota de los tarballs de sitios web y bases de datos.
- [ ] **3.3. Restauración 1-Click:** Lógica inversa al `BackupService` para restaurar dumps desde la propia UI.

## Fase 4: Experiencia UI Devops
*Estado: Pendiente*
**Prioridad:** MEDIA.
- [ ] **4.1. Logs del Sistema Paginados/Live:** Streaming en tiempo real (`tail -f`) de `/var/log` a través de Livewire streams.
- [ ] **4.2. Gestor Web de Base de Datos:** Embeber Adminer o phpMyAdmin.
- [ ] **4.3. SSL Wildcard (DNS-01):** Integración con `acme.sh` y PowerDNS local para resolver certs tipo `*.domain.com`.
- [ ] **4.4. Docker Compose UI / Scripts Marketplace:** Ejecutores rápidos de templates (WordPress, Node) con 1 click.

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
*Estado: Pendiente*
**Prioridad:** MEDIA-ALTA. (Añadido recientemente).
- [ ] **10.1. Under Attack Mode:** Toggle rápido que inyecta limitadores en el vhost.
- [ ] **10.2. FastCGI Microcaching:** Botón UI para cachear salida dinámica con modo "Purge Dev".
- [ ] **10.3. Geo-WAF (MaxMind):** Módulo Nginx para bloquear visitas internacionales por ISO Code.
- [ ] **10.4. Proxy DNS Inverso (Orange Cloud):** Hacer Nginx de proxy frontal para esconder backends o docker-containers (vhosts intermedios).
- [ ] **10.5. GoAccess Analytics:** Estadísticas visuales parseando los logs de Nginx, omitiendo dependencias de cookies o Google Analytics.
- [ ] **10.6. Page Rules y SSL Avanzado:** Opciones UI para inyectar Headers de seguridad (HSTS) y armar redirecciones nativas (301) sobre Nginx.
