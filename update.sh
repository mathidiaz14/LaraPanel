#!/usr/bin/env bash

# ==============================================================================
# Script de Actualización Automatizado de LaraPanel
# ==============================================================================
# Este script actualiza el panel a la última versión, instala dependencias de
# Composer y NPM, ejecuta migraciones, limpia las cachés y asegura los permisos.
# Debe ejecutarse como root o con privilegios sudo.
# ==============================================================================

set -euo pipefail

# Colores para salida informativa
RED='\033[0;31m'
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

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

# ─── Verificar que se ejecuta en el directorio correcto ────────────────────────
PANEL_DIR="/var/www/panel"

if [ ! -d "$PANEL_DIR" ]; then
    log_warn "No se encontró el directorio /var/www/panel. Usando el directorio actual como raíz del panel..."
    PANEL_DIR="$(pwd)"
fi

log_info "Iniciando actualización en: $PANEL_DIR"
cd "$PANEL_DIR"

# Leer el puerto interno Reverb del .env (fallback: 8081)
REVERB_SERVER_PORT=$(grep -E '^REVERB_SERVER_PORT=' "$PANEL_DIR/.env" 2>/dev/null | cut -d= -f2 | tr -d '"' | tr -d "'" || true)
REVERB_SERVER_PORT="${REVERB_SERVER_PORT:-8081}"

# ─── 0. Dependencias del sistema ───────────────────────────────────────────────
log_info "Verificando dependencias del sistema..."
apt-get update -qq || true
apt-get install -y -qq openssh-client util-linux sshpass supervisor || true

# ─── 1. Configurar git safe.directory ─────────────────────────────────────────
log_info "Configurando excepciones de seguridad en Git..."
git config --global --add safe.directory "$PANEL_DIR" || true

# ─── 2. Descargar última versión desde Git ────────────────────────────────────
log_info "Obteniendo los últimos cambios de Git..."
git fetch --all || true
git reset --hard origin/main 2>/dev/null || git reset --hard origin/master 2>/dev/null || log_warn "No se pudo realizar el reset de git. Intentando git pull estándar..."
git pull || log_warn "Git pull falló. Continuando con el resto del proceso..."

# ─── 3. Permisos temporales de larapanel:www-data ─────────────────────────────
log_info "Ajustando la propiedad de los archivos a larapanel:www-data..."
chown -R larapanel:www-data "$PANEL_DIR" || true
chmod -R 755 "$PANEL_DIR" || true

# ─── 4. Actualizar dependencias de PHP ────────────────────────────────────────
log_info "Instalando dependencias de PHP (Composer)..."
sudo -u larapanel composer install --no-dev --optimize-autoloader --no-interaction

# ─── 5. Ejecutar migraciones de Base de Datos ─────────────────────────────────
log_info "Ejecutando migraciones de base de datos..."
sudo -u larapanel php artisan migrate --force

# ─── 6. Limpiar y optimizar cachés de Laravel ─────────────────────────────────
log_info "Limpiando y optimizando configuraciones y cachés de Laravel..."
sudo -u larapanel php artisan config:clear || true
sudo -u larapanel php artisan cache:clear || true
sudo -u larapanel php artisan route:clear || true
sudo -u larapanel php artisan view:clear || true
sudo -u larapanel php artisan event:clear || true

# Optimizar para producción
sudo -u larapanel php artisan config:cache || true
sudo -u larapanel php artisan route:cache || true
sudo -u larapanel php artisan view:cache || true
sudo -u larapanel php artisan event:cache || true

# ─── 7. Instalar dependencias JS y compilar assets ────────────────────────────
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

# ─── 8. Asegurar permisos correctos finales ───────────────────────────────────
log_info "Configurando permisos finales..."
mkdir -p "$PANEL_DIR/storage/framework/"{sessions,views,cache/data} "$PANEL_DIR/storage/logs" "$PANEL_DIR/bootstrap/cache" "$PANEL_DIR/database"
touch "$PANEL_DIR/database/database.sqlite" || true

chown -R larapanel:www-data "$PANEL_DIR" || true
chmod -R 755 "$PANEL_DIR" || true

# storage, bootstrap/cache y database pertenecen a www-data y tienen permisos 777
chown -R www-data:www-data "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache" "$PANEL_DIR/database"
chmod -R 777 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache" "$PANEL_DIR/database"

# ─── 9. Reiniciar worker de colas ─────────────────────────────────────────────
log_info "Reiniciando el worker de colas (Queue Worker)..."
sudo -u larapanel php artisan queue:restart || true

# ─── 10. Configurar e Iniciar Supervisor (Queue Worker, Scheduler, Reverb) ───
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

[program:larapanel-reverb]
process_name=%(program_name)s
command=php ${PANEL_DIR}/artisan reverb:start --no-interaction
directory=${PANEL_DIR}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=${PANEL_DIR}/storage/logs/reverb.log
stopwaitsecs=10
SUP_EOF

    systemctl enable supervisor || true
    systemctl start supervisor || true
    supervisorctl reread || true
    supervisorctl update || true
    supervisorctl restart larapanel-worker:* 2>/dev/null || supervisorctl start larapanel-worker:* 2>/dev/null || true
    supervisorctl restart larapanel-reverb 2>/dev/null || supervisorctl start larapanel-reverb 2>/dev/null || true
    supervisorctl restart larapanel-scheduler 2>/dev/null || supervisorctl start larapanel-scheduler 2>/dev/null || true
else
    log_warn "supervisorctl no está disponible. No se pudo registrar la configuración de Supervisor."
fi

# Actualizar el puerto en la directiva proxy_pass de Nginx si el archivo del panel existe
if [ -f "/etc/nginx/sites-available/larapanel" ]; then
    log_info "Sincronizando el puerto proxy de Reverb (${REVERB_SERVER_PORT}) en la configuración de Nginx..."
    if ! grep -q "location /app" /etc/nginx/sites-available/larapanel; then
        sed -i '$ s|}|  location /app {\n    proxy_http_version 1.1;\n    proxy_set_header Upgrade $http_upgrade;\n    proxy_set_header Connection "Upgrade";\n    proxy_set_header Host $host;\n    proxy_set_header X-Real-IP $remote_addr;\n    proxy_pass http://127.0.0.1:'"${REVERB_SERVER_PORT}"';\n  }\n}|' /etc/nginx/sites-available/larapanel
    else
        sed -i -E "s|proxy_pass http://127.0.0.1:[0-9]+;|proxy_pass http://127.0.0.1:${REVERB_SERVER_PORT};|g" /etc/nginx/sites-available/larapanel
    fi
    nginx -t && systemctl reload nginx || true
fi

# ─── 11. Verificar que Reverb está escuchando ─────────────────────────────────
log_info "Verificando que Reverb responde en el puerto ${REVERB_SERVER_PORT}..."
REVERB_OK=0
REVERB_RETRIES=6  # 6 intentos × 5s = 30s máximo
for i in $(seq 1 $REVERB_RETRIES); do
    if ss -tlnp 2>/dev/null | grep -q ":${REVERB_SERVER_PORT}" || \
       netstat -tlnp 2>/dev/null | grep -q ":${REVERB_SERVER_PORT}"; then
        REVERB_OK=1
        break
    fi
    log_info "Esperando que Reverb inicie... intento ${i}/${REVERB_RETRIES}"
    sleep 5
done

if [ "$REVERB_OK" -eq 1 ]; then
    log_success "Reverb WebSocket está activo y escuchando en el puerto ${REVERB_SERVER_PORT}."
else
    log_warn "Reverb no detectado en el puerto ${REVERB_SERVER_PORT} después de 30s."
    log_warn "Puedes revisar los logs con: tail -f ${PANEL_DIR}/storage/logs/reverb.log"
    log_warn "O iniciar manualmente con: supervisorctl start larapanel-reverb"
fi

# ─── 12. Reiniciar PHP-FPM para vaciar OPcache ────────────────────────────────
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

# ─── 13. Reiniciar Nginx ──────────────────────────────────────────────────────
log_info "Reiniciando servidor Nginx..."
systemctl restart nginx || true

# ─── Resumen final ────────────────────────────────────────────────────────────
echo ""
log_success "¡LaraPanel se ha actualizado y optimizado correctamente a la última versión!"
echo ""
log_info "Estado de procesos Supervisor:"
if command -v supervisorctl > /dev/null 2>&1; then
    supervisorctl status larapanel-worker:* larapanel-reverb larapanel-scheduler 2>/dev/null || true
fi
echo ""
