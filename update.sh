#!/usr/bin/env bash

# ==============================================================================
# Script de Actualización Automatizado de LaraPanel
# ==============================================================================
# Este script actualiza el panel a la última versión, instala dependencias de 
# Composer y NPM, ejecuta migraciones, limpia las cachés y asegura los permisos.
# Debe ejecutarse como root o con privilegios sudo.
# ==============================================================================

set -e

# Colores para salida informativa
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0;3m' # No Color
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
    echo -e "${RED}[ERROR]${NC} $1"
}

# Verificar que se ejecuta en el directorio correcto
PANEL_DIR="/var/www/panel"

if [ ! -d "$PANEL_DIR" ]; then
    log_warn "No se encontró el directorio /var/www/panel. Usando el directorio actual como raíz del panel..."
    PANEL_DIR="$(pwd)"
fi

log_info "Iniciando actualización en: $PANEL_DIR"
cd "$PANEL_DIR"

# 1. Configurar git safe.directory para evitar bloqueos
log_info "Configurando excepciones de seguridad en Git..."
git config --global --add safe.directory "$PANEL_DIR" || true

# 2. Descargar última versión desde Git
log_info "Obteniendo los últimos cambios de Git..."
git fetch --all
git reset --hard origin/main || git reset --hard origin/master || log_warn "No se pudo realizar el reset de git. Intentando git pull estándar..."
git pull || log_warn "Git pull falló. Continuando con el resto del proceso..."

# 3. Permisos temporales de larapanel:www-data
log_info "Ajustando la propiedad de los archivos a larapanel:www-data..."
chown -R larapanel:www-data "$PANEL_DIR"
chmod -R 755 "$PANEL_DIR"

# 4. Actualizar dependencias de PHP
log_info "Instalando dependencias de PHP (Composer)..."
sudo -u larapanel composer install --no-dev --optimize-autoloader --no-interaction

# 5. Ejecutar migraciones de Base de Datos
log_info "Ejecutando migraciones de base de datos..."
sudo -u larapanel php artisan migrate --force

# 6. Limpiar y optimizar cachés de Laravel
log_info "Limpiando y optimizando configuraciones y cachés de Laravel..."
sudo -u larapanel php artisan config:clear
sudo -u larapanel php artisan cache:clear
sudo -u larapanel php artisan route:clear
sudo -u larapanel php artisan view:clear

# Optimizar para producción
sudo -u larapanel php artisan config:cache
sudo -u larapanel php artisan route:cache
sudo -u larapanel php artisan view:cache

# 7. Instalar dependencias JS y compilar assets
if [ -f "package.json" ]; then
    log_info "Instalando dependencias de Node.js y compilando assets con Vite..."
    sudo -u larapanel npm install
    sudo -u larapanel npm run build
else
    log_warn "No se encontró package.json. Omitiendo compilación de assets..."
fi

# 8. Asegurar permisos correctos finales (Muy importante para evitar errores 500 y de FileManager)
log_info "Configurando permisos finales..."
chown -R larapanel:www-data "$PANEL_DIR"
chmod -R 755 "$PANEL_DIR"

# Asegurar que storage y bootstrap/cache tengan permisos de escritura para el grupo www-data (servidor web)
chmod -R 775 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
chown -R larapanel:www-data "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

# 9. Reiniciar servicios en ejecución
log_info "Reiniciando el worker de colas (Queue Worker)..."
sudo -u larapanel php artisan queue:restart || true

# Buscar versión activa de PHP-FPM y reiniciar para vaciar OPcache
PHP_FPM_SERVICE=$(systemctl list-units --type=service --all | grep -oE "php[0-9]+\.[0-9]+-fpm" | head -n 1)
if [ -n "$PHP_FPM_SERVICE" ]; then
    log_info "Reiniciando servicio PHP-FPM ($PHP_FPM_SERVICE) para aplicar cambios en OPcache..."
    systemctl restart "$PHP_FPM_SERVICE"
else
    log_warn "No se pudo detectar el servicio PHP-FPM automáticamente. Intenta reiniciarlo manualmente."
fi

# Reiniciar Nginx si es necesario
log_info "Reiniciando servidor Nginx..."
systemctl restart nginx

log_success "¡LaraPanel se ha actualizado y optimizado correctamente a la última versión!"
