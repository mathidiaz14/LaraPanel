# Contexto Funcional (LaraPanel)

## 1. Propósito del Proyecto
LaraPanel es un panel de control de servidor web tipo cPanel/Plesk, auto-hospedado, construido sobre Laravel 11. Se comunica directamente con el sistema operativo Linux del servidor a través de una capa de ejecución de comandos privilegiada y controlada (`SudoExecutor`). Su interfaz de usuario es altamente interactiva, utilizando un diseño Glassmorphism oscuro, reactividad completa con Livewire 4.x + Alpine.js y diseño 100% responsivo.

## 2. Flujos de Usuario Implementados
- **Gestión de Dominios:** Crear dominios, subdominios y alias. Generación automática de `vhosts` en Nginx (y soporte base para Apache), directorios web y pools de PHP-FPM por sitio.
- **SSL / TLS:** Emisión de certificados Let's Encrypt sin downtime (HTTP-01 vía `acme.sh`), instalación de certificados personalizados o auto-firmados, con auto-renovación cronificada.
- **Gestión de Archivos:** Explorador estilo IDE con árbol de directorios, editor integrado (Monaco Editor), carga con barra de progreso, extracción/compresión de `.zip` en tiempo real (streaming a UI).
- **Email:** Administración de cuentas Postfix/Dovecot, alias, autorespondedores, firmas DKIM y métricas de envío/recepción.
- **Bases de Datos:** Creación de bases de datos MySQL, usuarios con contraseñas generadas y asignación de permisos `GRANT ALL`.
- **DNS Manager:** Integración nativa con PowerDNS vía API REST para gestionar zonas y registros.
- **Seguridad Perimetral:** Gestión visual del firewall UFW (presets, reglas personalizadas), visor de "jails" de Fail2ban (desbaneo de IPs), Antispam (Rspamd) y Antivirus (ClamAV con cuarentena).
- **Backups y Tareas Programadas (Cron):** Editor visual de expresiones cron, copias de seguridad de BD y archivos en tarballs.
- **Git Deploy (CI/CD):** Webhooks verificados por firma HMAC para GitHub/GitLab, pull y despliegue automático tras cada push al branch principal, con streaming de logs.
- **Docker y PHP:** Administrador visual de contenedores Docker (stats, logs, start/stop) y gestor de versiones de PHP por dominio.
- **Terminal y Logs:** Consola web en tiempo real (solo admins) sujeta a una estricta whitelist de comandos, y visor de logs del sistema paginado.
- **Dashboard y Multi-Servidor:** Visor de métricas en vivo (CPU, RAM, Disco, Red) leyendo directamente de `/proc`. Permite registrar múltiples servidores remotos (conexión SSH vía phpseclib3) y conmutar el panel para operarlos.

## 3. Módulos/Funcionalidades Operativos
- `Dashboard`, `Domains`, `SSL`, `Files`, `Email`, `Databases`, `FTP`, `DNS`, `Firewall`, `Fail2ban`, `Antispam`, `Antivirus`, `Cron`, `Backups`, `Git`, `Docker`, `PHP`, `Terminal`, `Logs`, `WordPress`, `Servers` (Multi-servidor) y módulo `Admin` (gestión de usuarios y cuotas por Plan). Todo el núcleo base está operativo y probado.

## 4. Ideas de Funcionalidades Futuras
- **SaaS y Resellers (Multi-Tenant):** Implementar la lógica para revendedores (fase 5).
- **Protección tipo Cloudflare (WAF/CDN):** Modo "Bajo Ataque", Microcaching FastCGI de Nginx, Proxy Inverso (Orange Cloud), Auto-Minify, y Bloqueo GeoIP (Geo-WAF).
- **Backups Externos (Nube):** Enviar copias a AWS S3 o Backblaze B2 directamente desde el servidor.
- **Notificaciones (Telegram/Mail):** Alertar al administrador cuando la CPU o RAM superen umbrales definidos (90%).
- **Marketplace "One-Click":** Instaladores rápidos de stacks (LAMP, Node.js, Redis) estilo Softaculous.
