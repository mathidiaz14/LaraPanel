#!/usr/bin/env bash

# ==============================================================================
# Script de Actualización Automatizado de LaraPanel
# ==============================================================================
# Este script actualiza el panel, instala dependencias, ejecuta migraciones,
# compila los assets y reinicia los servicios necesarios.
# Debe ejecutarse como root o con privilegios sudo.
# ==============================================================================

set -euo pipefail

# Colores para salida informativa
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color
BLUE='\033[0;34m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[ÉXITO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[ADVERTENCIA]${NC} $1"
}

# ─── Verificar que se ejecuta en el directorio correcto ────────────────────────
PANEL_DIR="/var/www/panel"

if [ ! -d "$PANEL_DIR" ]; then
    log_warn "No se encontró el directorio /var/www/panel. Usando el directorio actual como raíz del panel..."
    PANEL_DIR="$(pwd)"
fi

log_info "Iniciando actualización en: $PANEL_DIR"
cd "$PANEL_DIR"

# ─── 1. Configurar git safe.directory ─────────────────────────────────────────
log_info "Configurando excepciones de seguridad en Git..."
git config --global --add safe.directory "$PANEL_DIR" || true

# ─── 2. Descargar última versión desde Git ────────────────────────────────────
log_info "Obteniendo los últimos cambios de Git..."
git fetch origin main --quiet || git fetch origin master --quiet || true
git reset --hard origin/main 2>/dev/null || git reset --hard origin/master 2>/dev/null || log_warn "No se pudo actualizar el código desde Git."

# ─── 3. Actualizar dependencias de PHP ────────────────────────────────────────
log_info "Instalando dependencias de PHP (Composer)..."
sudo -u larapanel composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# ─── 4. Ejecutar migraciones de Base de Datos ─────────────────────────────────
log_info "Ejecutando migraciones de base de datos..."
sudo -u larapanel php artisan migrate --force

# ─── 5. Optimizar cachés de Laravel ───────────────────────────────────────────
log_info "Optimizando cachés de Laravel..."
sudo -u larapanel php artisan optimize

# ─── 6. Instalar dependencias JS y compilar assets ────────────────────────────
if [ -f "package.json" ]; then
    log_info "Instalando dependencias de Node.js y compilando assets con Vite..."
    if [ -f "package-lock.json" ]; then
        sudo -u larapanel npm ci || sudo -u larapanel npm install
    else
        sudo -u larapanel npm install
    fi
    sudo -u larapanel npm run build || log_warn "Falló la compilación de assets Vite."
else
    log_warn "No se encontró package.json. Omitiendo compilación de assets..."
fi

# ─── 7. Asegurar permisos de directorios escribibles ──────────────────────────
log_info "Configurando permisos finales..."
mkdir -p "$PANEL_DIR/storage/framework/"{sessions,views,cache/data} "$PANEL_DIR/storage/logs" "$PANEL_DIR/bootstrap/cache" "$PANEL_DIR/database"
touch "$PANEL_DIR/database/database.sqlite" || true

chown -R www-data:www-data "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache" "$PANEL_DIR/database"
chmod -R 777 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache" "$PANEL_DIR/database"

# ─── 8. Reiniciar worker de colas ─────────────────────────────────────────────
log_info "Reiniciando el worker de colas (Queue Worker)..."
sudo -u larapanel php artisan queue:restart || true

# ─── 9. Configurar e iniciar Supervisor (Queue Worker, Scheduler) ─────────────
log_info "Configurando y registrando servicios en Supervisor..."

if command -v supervisorctl > /dev/null 2>&1; then
    mkdir -p /etc/supervisor/conf.d

    cat > /etc/supervisor/conf.d/larapanel.conf <<SUP_EOF
[program:larapanel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${PANEL_DIR}/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=larapanel
numprocs=2
redirect_stderr=true
stdout_logfile=${PANEL_DIR}/storage/logs/worker.log
stopwaitsecs=3600

[program:larapanel-scheduler]
process_name=%(program_name)s
command=/bin/bash -c 'while true; do php ${PANEL_DIR}/artisan schedule:run --no-interaction >> /dev/null 2>&1; sleep 60; done'
autostart=true
autorestart=true
user=larapanel
redirect_stderr=true
stdout_logfile=${PANEL_DIR}/storage/logs/scheduler.log

SUP_EOF

    systemctl enable supervisor || true
    systemctl start supervisor || true
    supervisorctl reread || true
    supervisorctl update || true
    supervisorctl restart larapanel-worker:* 2>/dev/null || supervisorctl start larapanel-worker:* 2>/dev/null || true
    supervisorctl restart larapanel-scheduler 2>/dev/null || supervisorctl start larapanel-scheduler 2>/dev/null || true
else
    log_warn "supervisorctl no está disponible. No se pudo registrar la configuración de Supervisor."
fi

# ─── 10. Reiniciar PHP-FPM para vaciar OPcache ─────────────────────────────────
log_info "Reiniciando servicios PHP-FPM activos..."
FPM_SERVICES=$(systemctl list-units --type=service --state=running 2>/dev/null | grep -oE "php[0-9]+\.[0-9]+-fpm" || true)
if [ -n "$FPM_SERVICES" ]; then
    for fpm in $FPM_SERVICES; do
        log_info "Reiniciando $fpm..."
        systemctl restart "$fpm" || true
    done
else
    log_warn "No se detectaron servicios PHP-FPM activos para reiniciar."
fi

# ─── Resumen final ────────────────────────────────────────────────────────────
echo ""
log_success "¡LaraPanel se ha actualizado y optimizado correctamente a la última versión!"
echo ""
log_info "Estado de procesos Supervisor:"
if command -v supervisorctl > /dev/null 2>&1; then
    supervisorctl status larapanel-worker:* larapanel-scheduler 2>/dev/null || true
fi
echo ""
