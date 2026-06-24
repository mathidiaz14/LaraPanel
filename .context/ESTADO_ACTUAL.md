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

De acuerdo a la planificación y las instrucciones maestras en `fases_de_implementacion.md`, **la prioridad máxima que bloquea una liberación segura a producción es concluir la Fase 1: Estabilización.**

Las tareas exactas para los programadores en el siguiente commit son:
1. Abrir `routes/api.php` y `routes/web.php`.
2. Proteger la ruta del webhook de despliegue de Git y las pantallas de Login de Fortify aplicando un middleware estricto de Rate Limiting (ej: `->middleware('throttle:60,1')`).
3. Someter a todos los `Livewire Components` a un barrido de sus atributos `$rules` para garantizar validación exhaustiva de los inputs antes de que estos pasen a `ShellExecutor`.
