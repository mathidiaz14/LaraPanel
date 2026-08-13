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

# Leer el puerto Reverb del .env (fallback: 8080)
REVERB_SERVER_PORT=$(grep -E '^REVERB_SERVER_PORT=' "$PANEL_DIR/.env" 2>/dev/null | cut -d= -f2 | tr -d '"' | tr -d "'")
REVERB_SERVER_PORT="${REVERB_SERVER_PORT:-8080}"

# ─── 0. Dependencias del sistema ───────────────────────────────────────────────
log_info "Verificando dependencias del sistema..."
apt-get update -qq
apt-get install -y -qq openssh-client util-linux sshpass

# ─── 1. Configurar git safe.directory ─────────────────────────────────────────
log_info "Configurando excepciones de seguridad en Git..."
git config --global --add safe.directory "$PANEL_DIR" || true

# ─── 2. Descargar última versión desde Git ────────────────────────────────────
log_info "Obteniendo los últimos cambios de Git..."
git fetch --all
git reset --hard origin/main || git reset --hard origin/master || log_warn "No se pudo realizar el reset de git. Intentando git pull estándar..."
git pull || log_warn "Git pull falló. Continuando con el resto del proceso..."

# ─── 3. Permisos temporales de larapanel:www-data ─────────────────────────────
log_info "Ajustando la propiedad de los archivos a larapanel:www-data..."
chown -R larapanel:www-data "$PANEL_DIR"
chmod -R 755 "$PANEL_DIR"

# ─── 4. Actualizar dependencias de PHP ────────────────────────────────────────
log_info "Instalando dependencias de PHP (Composer)..."
sudo -u larapanel composer install --no-dev --optimize-autoloader --no-interaction

# ─── 5. Ejecutar migraciones de Base de Datos ─────────────────────────────────
log_info "Ejecutando migraciones de base de datos..."
sudo -u larapanel php artisan migrate --force

# ─── 6. Limpiar y optimizar cachés de Laravel ─────────────────────────────────
log_info "Limpiando y optimizando configuraciones y cachés de Laravel..."
sudo -u larapanel php artisan config:clear
sudo -u larapanel php artisan cache:clear
sudo -u larapanel php artisan route:clear
sudo -u larapanel php artisan view:clear
sudo -u larapanel php artisan event:clear

# Optimizar para producción
sudo -u larapanel php artisan config:cache
sudo -u larapanel php artisan route:cache
sudo -u larapanel php artisan view:cache
sudo -u larapanel php artisan event:cache

# ─── 7. Instalar dependencias JS y compilar assets ────────────────────────────
if [ -f "package.json" ]; then
    log_info "Instalando dependencias de Node.js y compilando assets con Vite..."
    # Usar npm ci para instalación reproducible en producción
    if [ -f "package-lock.json" ]; then
        sudo -u larapanel npm ci --omit=dev
    else
        sudo -u larapanel npm install --omit=dev
    fi
    sudo -u larapanel npm run build
else
    log_warn "No se encontró package.json. Omitiendo compilación de assets..."
fi

# ─── 8. Asegurar permisos correctos finales ───────────────────────────────────
log_info "Configurando permisos finales..."
chown -R larapanel:www-data "$PANEL_DIR"
chmod -R 755 "$PANEL_DIR"

# storage y bootstrap/cache necesitan permisos de escritura para el grupo www-data
chmod -R 775 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
chown -R larapanel:www-data "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

# ─── 9. Reiniciar worker de colas ─────────────────────────────────────────────
log_info "Reiniciando el worker de colas (Queue Worker)..."
sudo -u larapanel php artisan queue:restart || true

# ─── 10. Reiniciar Reverb vía Supervisor ──────────────────────────────────────
log_info "Gestionando el proceso WebSocket Reverb..."

if command -v supervisorctl > /dev/null 2>&1; then
    supervisorctl reread || true
    supervisorctl update || true

    # Reiniciar el proceso Reverb
    if supervisorctl status larapanel-reverb > /dev/null 2>&1; then
        log_info "Reiniciando proceso Reverb en Supervisor..."
        supervisorctl restart larapanel-reverb || log_warn "No se pudo reiniciar larapanel-reverb vía supervisorctl."
    else
        log_warn "El programa larapanel-reverb no existe en Supervisor. Intentando iniciarlo..."
        supervisorctl start larapanel-reverb || log_warn "No se pudo iniciar larapanel-reverb."
    fi
else
    log_warn "supervisorctl no está disponible. Verificando si Reverb está corriendo vía artisan..."
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
PHP_FPM_SERVICE=$(systemctl list-units --type=service --all 2>/dev/null | grep -oE "php[0-9]+\.[0-9]+-fpm" | head -n 1 || true)
if [ -n "$PHP_FPM_SERVICE" ]; then
    log_info "Reiniciando servicio PHP-FPM ($PHP_FPM_SERVICE) para aplicar cambios en OPcache..."
    systemctl restart "$PHP_FPM_SERVICE"
else
    log_warn "No se pudo detectar el servicio PHP-FPM automáticamente. Intenta reiniciarlo manualmente."
fi

# ─── 13. Reiniciar Nginx ──────────────────────────────────────────────────────
log_info "Reiniciando servidor Nginx..."
systemctl restart nginx

# ─── Resumen final ────────────────────────────────────────────────────────────
echo ""
log_success "¡LaraPanel se ha actualizado y optimizado correctamente a la última versión!"
echo ""
log_info "Estado de procesos Supervisor:"
if command -v supervisorctl > /dev/null 2>&1; then
    supervisorctl status larapanel-worker larapanel-reverb larapanel-scheduler 2>/dev/null || true
fi
echo ""
