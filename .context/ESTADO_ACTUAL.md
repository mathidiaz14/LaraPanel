# ESTADO ACTUAL (LaraPanel)

*Diagnóstico de la base de código actual (v0.1.0-alpha) contrastado con la Arquitectura Maestra.*

## 1. Análisis Estructural 

La arquitectura monolítica definida en la documentación funciona exactamente como fue proyectada. El patrón de aislar los comandos de shell a través de `ShellExecutor` y restringirlos severamente con un whitelist de sudo ha probado ser un método estable y seguro para abstraer a Linux. La transición del estado frontend al backend mediante Livewire 4.x proporciona una experiencia fluida tipo SPA real, libre de recargas completas.

### Módulos 100% Consolidados y Robustos
- **Arquitectura Base & Seguridad:** El bloque de validación (`App\Shell\SudoExecutor`) junto con la detección de Path Traversal (`FileService::resolvePath()`) funcionan. Las acciones peligrosas quedan guardadas consistentemente en `AuditLog`. Los permisos y tokens de usuario y webhooks Git con HMAC SHA-256 completan una capa fortificada.
- **Dominios, Nginx y SSL:** El motor de renderizado de vhosts soporta múltiples modos (alias, web principal, redirecciones). La emisión y renovación cronificada de certificados con `acme.sh` por webroot opera en el backend de forma correcta.
- **Bases de Datos, Correo, DNS, Firewall (UFW):** Se crean usuarios, se abren/cierran puertos y se aplican bloqueos locales adecuadamente.

### Funcionalidades Presentes que Requieren Refactorización o Madurez
1. **Gestor de Archivos (File Manager):**
   - Aunque se introdujo la validación `resolvePath()` y funciona bien, abusar de los comandos Bash crudos (`mv`, `cp`, `rm`) como usuario web proxy choca constantemente contra permisos locales. Una migración agresiva hacia `Illuminate\Support\Facades\Storage` (Driver local) saneará significativamente los problemas de Ownership (`www-data`).
2. **Sistema de Backups Local:**
   - Construye Tarballs y hace dump a SQL correctamente, pero el código subyacente para transferirlos hacia `S3` o la nube no está activado / interconectado todavía (Fase 3).
3. **Métricas Estáticas:**
   - Se captura muy bien el `Dashboard` en vivo pero el volcado a la tabla histórica `ServerMetric` requiere de un Cron programado persistente que actualmente no está vivo (Fase 2).

## 2. El Próximo Paso Técnico Inmediato

La **Fase 1: Estabilización** ha sido completada exitosamente. Se ha aplicado el Rate Limiting estricto a las rutas públicas (Webhooks, Login) y se ha saneado la validación de todos los componentes de Livewire mediante expresiones regulares para evitar inyecciones Bash a través de `ShellExecutor`.

De acuerdo a la hoja de ruta en `fases_de_implementacion.md`, el **próximo paso técnico prioritario es iniciar la Fase 2: Notificaciones y Monitoreo Histórico.**

Las tareas propuestas para continuar son:
1. Crear el sistema de notificaciones base de Laravel para poder disparar alertas vía Email o Telegram.
2. Escribir un Cron en el backend (`Console/Commands`) que procese `ServerMetric` y alerte si la CPU o RAM superan umbrales críticos.
3. Crear un script que persista periódicamente el estado actual del servidor para graficar métricas históricas de 24hs/7d en el Dashboard.
