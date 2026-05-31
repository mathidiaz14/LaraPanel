#!/usr/bin/env bash
# ==============================================================================
#  LaraPanel — Instalador Automático para VPS
#  Compatible con: Ubuntu 22.04 LTS / Ubuntu 24.04 LTS
#  Uso: sudo bash install.sh
# ==============================================================================

set -euo pipefail
IFS=$'\n\t'

# ── Colores ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# ── Funciones de log ──────────────────────────────────────────────────────────
info()    { echo -e "${BLUE}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*" >&2; exit 1; }
step()    { echo -e "\n${BOLD}${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"; \
            echo -e "${BOLD}${CYAN}  $*${NC}"; \
            echo -e "${BOLD}${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"; }

# ── Verificaciones iniciales ──────────────────────────────────────────────────
if [[ $EUID -ne 0 ]]; then
    error "Este script debe ejecutarse como root: sudo bash install.sh"
fi

OS_ID=$(grep -oP '(?<=^ID=).+' /etc/os-release | tr -d '"')
OS_VER=$(grep -oP '(?<=^VERSION_ID=).+' /etc/os-release | tr -d '"')
if [[ "$OS_ID" != "ubuntu" ]]; then
    error "Este instalador solo soporta Ubuntu. Sistema detectado: $OS_ID"
fi
if [[ "$OS_VER" != "22.04" && "$OS_VER" != "24.04" ]]; then
    warn "Versión de Ubuntu no probada: $OS_VER. Se recomienda 22.04 o 24.04."
fi

# ══════════════════════════════════════════════════════════════════════════════
#   BIENVENIDA
# ══════════════════════════════════════════════════════════════════════════════
clear
echo -e "${BOLD}${CYAN}"
echo "  ██╗      █████╗ ██████╗  █████╗ "
echo "  ██║     ██╔══██╗██╔══██╗██╔══██╗"
echo "  ██║     ███████║██████╔╝███████║"
echo "  ██║     ██╔══██║██╔══██╗██╔══██║"
echo "  ███████╗██║  ██║██║  ██║██║  ██║"
echo "  ╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝╚═╝  ╚═╝"
echo -e "${NC}${BOLD}         Panel  ·  Instalador VPS${NC}"
echo ""

# ══════════════════════════════════════════════════════════════════════════════
#   VARIABLES DE CONFIGURACIÓN — SE PEDIRÁN AL USUARIO
# ══════════════════════════════════════════════════════════════════════════════
step "Configuración inicial"

read -rp "  ► Dominio del panel (ej: panel.tudominio.com): " PANEL_DOMAIN
[[ -z "$PANEL_DOMAIN" ]] && error "El dominio no puede estar vacío."

read -rp "  ► Email del administrador: " ADMIN_EMAIL
[[ -z "$ADMIN_EMAIL" ]] && error "El email no puede estar vacío."

read -rsp "  ► Contraseña del administrador del panel: " ADMIN_PASSWORD
echo ""
[[ ${#ADMIN_PASSWORD} -lt 8 ]] && error "La contraseña debe tener al menos 8 caracteres."

read -rsp "  ► Contraseña para el usuario MySQL 'larapanel': " DB_PASSWORD
echo ""
[[ -z "$DB_PASSWORD" ]] && error "La contraseña de base de datos no puede estar vacía."

read -rp "  ► ¿Instalar SSL con Let's Encrypt? [S/n]: " INSTALL_SSL
INSTALL_SSL="${INSTALL_SSL:-S}"

read -rp "  ► Directorio de instalación [/var/www/larapanel]: " INSTALL_DIR
INSTALL_DIR="${INSTALL_DIR:-/var/www/larapanel}"

PANEL_USER="larapanel"
DB_NAME="larapanel_db"
PHP_VERSION="8.3"

echo ""
echo -e "${BOLD}Resumen de configuración:${NC}"
echo -e "  • Dominio:         ${GREEN}$PANEL_DOMAIN${NC}"
echo -e "  • Admin email:     ${GREEN}$ADMIN_EMAIL${NC}"
echo -e "  • Directorio:      ${GREEN}$INSTALL_DIR${NC}"
echo -e "  • PHP versión:     ${GREEN}$PHP_VERSION${NC}"
echo -e "  • SSL:             ${GREEN}$INSTALL_SSL${NC}"
echo ""
read -rp "¿Continuar con la instalación? [S/n]: " CONFIRM
CONFIRM="${CONFIRM:-S}"
[[ "${CONFIRM,,}" != "s" ]] && { info "Instalación cancelada."; exit 0; }

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 1 — ACTUALIZAR EL SISTEMA
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 1 — Actualizando el sistema"

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq
apt-get install -y -qq \
    curl git unzip wget zip software-properties-common \
    gnupg2 ca-certificates lsb-release apt-transport-https \
    supervisor cron fail2ban ufw

success "Sistema actualizado."

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 2 — NGINX
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 2 — Instalando Nginx"

apt-get install -y -qq nginx
systemctl enable nginx
systemctl start nginx
success "Nginx instalado y corriendo."

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 3 — PHP
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 3 — Instalando PHP $PHP_VERSION"

# Agregar el PPA de PHP manualmente con curl para evitar bloqueos del keyserver vía HKP (frecuentes en IPv6)
if [[ ! -f "/etc/apt/trusted.gpg.d/ondrej-php.gpg" ]]; then
    info "Descargando clave GPG para PHP..."
    curl -sS "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x4F4EA0AAE5267A6C" | gpg --dearmor -o /etc/apt/trusted.gpg.d/ondrej-php.gpg
fi
echo "deb https://ppa.launchpadcontent.net/ondrej/php/ubuntu $(lsb_release -cs) main" > /etc/apt/sources.list.d/ondrej-php.list

apt-get update -qq
apt-get install -y -qq \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-cli" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-sqlite3" \
    "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-intl" \
    "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-tokenizer" \
    "php${PHP_VERSION}-pdo"

# Instalar versiones adicionales para el módulo PHP multi-versión
for V in 8.1 8.2; do
    if [[ "$V" != "$PHP_VERSION" ]]; then
        info "Instalando PHP $V (soporte multi-versión)..."
        apt-get install -y -qq "php${V}-fpm" "php${V}-cli" "php${V}-mysql" "php${V}-mbstring" "php${V}-xml" 2>/dev/null || warn "PHP $V no disponible, continuando..."
    fi
done

systemctl enable "php${PHP_VERSION}-fpm"
systemctl start "php${PHP_VERSION}-fpm"
success "PHP $PHP_VERSION instalado."

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 4 — MYSQL
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 4 — Instalando MySQL"

apt-get install -y -qq mysql-server
systemctl enable mysql
systemctl start mysql

# Crear base de datos y usuario para LaraPanel
mysql -u root <<MYSQL_EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${PANEL_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${PANEL_USER}'@'localhost';
GRANT SUPER ON *.* TO '${PANEL_USER}'@'localhost';
FLUSH PRIVILEGES;
MYSQL_EOF

success "MySQL configurado. DB: ${DB_NAME}, Usuario: ${PANEL_USER}"

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 5 — NODE.JS Y COMPOSER
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 5 — Instalando Node.js y Composer"

# Node.js 22
if ! command -v node &>/dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash - 2>/dev/null
    apt-get install -y -qq nodejs
fi
success "Node.js $(node -v) instalado."

# Composer
if ! command -v composer &>/dev/null; then
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        rm composer-setup.php
        error "Checksum de Composer inválido."
    fi
    php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm composer-setup.php
fi
success "Composer $(composer --version --no-ansi | awk '{print $3}') instalado."

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 5.5 — INSTALANDO DOCKER Y DOCKER COMPOSE
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 5.5 — Instalando Docker y Docker Compose"

if ! command -v docker &>/dev/null; then
    info "Instalando dependencias de Docker..."
    apt-get install -y -qq ca-certificates curl gnupg
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg --yes
    chmod a+r /etc/apt/keyrings/docker.gpg

    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
      $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
      tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt-get update -qq
    apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi

systemctl enable docker
systemctl start docker

# Crear directorio base para Docker Compose de LaraPanel
mkdir -p /var/larapanel/compose
chown -R www-data:www-data /var/larapanel/compose
chmod -R 775 /var/larapanel/compose

success "Docker y Docker Compose instalados."

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 5.6 — INSTALANDO CLAMAV (ANTIVIRUS)
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 5.6 — Instalando ClamAV (Motor Antivirus)"

apt-get install -y -qq clamav clamav-daemon

# Crear directorio de cuarentena
mkdir -p /var/larapanel/quarantine
chown www-data:www-data /var/larapanel/quarantine
chmod 750 /var/larapanel/quarantine

# Detener clamav-freshclam para poder actualizar manualmente
systemctl stop clamav-freshclam 2>/dev/null || true

# Descargar definiciones iniciales
info "Descargando definiciones de virus (puede tardar unos minutos)..."
freshclam --quiet 2>/dev/null || warn "No se pudieron descargar las definiciones. Ejecuta 'freshclam' manualmente."

# Habilitar y arrancar el daemon
systemctl enable clamav-daemon
systemctl start clamav-daemon  || warn "El daemon ClamAV no pudo iniciarse. Verifica con: systemctl status clamav-daemon"
systemctl enable clamav-freshclam
systemctl start clamav-freshclam

# Configurar actualizón automática diaria vía cron
cat > /etc/cron.d/clamav-update << 'CRON_EOF'
# Actualizar definiciones de ClamAV cada noche a las 2 AM
0 2 * * * root /usr/bin/freshclam --quiet 2>/dev/null
CRON_EOF

success "ClamAV instalado y configurado. Cuarentena: /var/larapanel/quarantine"

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 5.7 — INSTALANDO RSPAMD (ANTISPAM)
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 5.7 — Instalando Rspamd (Motor Antispam)"

# Agregar repositorio oficial de Rspamd
if ! command -v rspamd &>/dev/null; then
    info "Agregando repositorio oficial de Rspamd..."
    curl -fsSL https://rspamd.com/apt-stable/gpg.key | gpg --dearmor -o /usr/share/keyrings/rspamd.gpg --yes
    echo "deb [signed-by=/usr/share/keyrings/rspamd.gpg] https://rspamd.com/apt-stable/ $(lsb_release -cs) main" \
        > /etc/apt/sources.list.d/rspamd.list
    apt-get update -qq
    apt-get install -y -qq rspamd redis-server
fi

# Asegurarse que Redis está corriendo (Rspamd lo usa para Bayes y rate limiting)
systemctl enable redis-server
systemctl start redis-server

# Configurar Rspamd: habilitar la interfaz web y establecer una contraseña
RSPAMD_PASSWORD=$(python3 -c "import secrets; print(secrets.token_urlsafe(24))")

mkdir -p /etc/rspamd/local.d

# Configurar el worker del proxy para escuchar en el socket local
cat > /etc/rspamd/local.d/worker-controller.inc << 'RSPAMD_CTRL_EOF'
bind_socket = "localhost:11334";
password = "$2$";
Enable = yes;
RSPAMD_CTRL_EOF

# Configurar Redis como backend de Bayes y estadisticas
cat > /etc/rspamd/local.d/redis.conf << 'RSPAMD_REDIS_EOF'
servers = "127.0.0.1:6379";
RSPAMD_REDIS_EOF

# Habilitar classifier Bayes con Redis
cat > /etc/rspamd/local.d/classifier-bayes.conf << 'RSPAMD_BAYES_EOF'
backend = "redis";
autolearn = true;
RSPAMD_BAYES_EOF

# Habilitar módulo de historial en Redis (necesario para el módulo del panel)
cat > /etc/rspamd/local.d/history_redis.conf << 'RSPAMD_HIST_EOF'
enable = true;
max_rows = 1000;
RSPAMD_HIST_EOF

# Generar contraseña con hash para la API web de Rspamd
info "Generando contraseña para la API de Rspamd..."
RSPAMD_HASH=$(rspamadm pw -p "${RSPAMD_PASSWORD}" 2>/dev/null || echo "")
if [[ -n "$RSPAMD_HASH" ]]; then
    # Reemplazar el placeholder en el archivo de configuracion
    sed -i "s|password = \"\$2\$\".*|password = \"${RSPAMD_HASH}\";|" /etc/rspamd/local.d/worker-controller.inc
fi

# Crear directorios de configuracion para reglas del panel
mkdir -p /etc/rspamd/local.d
touch /etc/rspamd/local.d/larapanel_whitelist.conf
touch /etc/rspamd/local.d/larapanel_blacklist.conf
chmod 644 /etc/rspamd/local.d/larapanel_whitelist.conf
chmod 644 /etc/rspamd/local.d/larapanel_blacklist.conf

# Habilitar y arrancar
systemctl enable rspamd
systemctl restart rspamd

# Guardar la contraseña en el .env de LaraPanel (se sobreescribirá si ya existe)
if [[ -f "${INSTALL_DIR}/.env" ]]; then
    if grep -q "RSPAMD_PASSWORD" "${INSTALL_DIR}/.env"; then
        sed -i "s/RSPAMD_PASSWORD=.*/RSPAMD_PASSWORD=${RSPAMD_PASSWORD}/" "${INSTALL_DIR}/.env"
    else
        echo "RSPAMD_PASSWORD=${RSPAMD_PASSWORD}" >> "${INSTALL_DIR}/.env"
    fi
fi

success "Rspamd instalado. API local en http://127.0.0.1:11334"

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 6 — USUARIO DEL SISTEMA
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 6 — Creando usuario del sistema '${PANEL_USER}'"

if ! id -u "$PANEL_USER" &>/dev/null; then
    adduser --disabled-password --gecos "" "$PANEL_USER"
    success "Usuario '$PANEL_USER' creado."
else
    info "El usuario '$PANEL_USER' ya existe, continuando..."
fi

usermod -aG www-data "$PANEL_USER"
usermod -aG docker "$PANEL_USER"
usermod -aG docker www-data

success "Usuario '$PANEL_USER' configurado."

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 7 — COPIAR ARCHIVOS DEL PANEL
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 7 — Desplegando archivos de LaraPanel"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

mkdir -p "$INSTALL_DIR"

# Si el script está dentro del proyecto, copiar desde aquí
if [[ -f "${SCRIPT_DIR}/artisan" ]]; then
    info "Copiando archivos desde: $SCRIPT_DIR → $INSTALL_DIR"
    rsync -a --exclude='.git' --exclude='node_modules' --exclude='storage/logs/*.log' \
        "${SCRIPT_DIR}/" "${INSTALL_DIR}/"
else
    warn "No se encontró el proyecto en $SCRIPT_DIR."
    warn "Debes copiar manualmente los archivos del proyecto a $INSTALL_DIR"
    warn "Puedes usar: rsync -avz /ruta/local/panel/ ${PANEL_USER}@$(hostname -I | awk '{print $1}'):${INSTALL_DIR}/"
    read -rp "  ► ¿Ya copiaste los archivos? Presiona ENTER para continuar o Ctrl+C para cancelar."
fi

chown -R "${PANEL_USER}:www-data" "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
success "Archivos en su lugar."

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 8 — CONFIGURACIÓN DE LA APLICACIÓN LARAVEL
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 8 — Configurando la aplicación Laravel"

cd "$INSTALL_DIR"

# Generar .env de producción
cat > "${INSTALL_DIR}/.env" <<ENV_EOF
APP_NAME=LaraPanel
APP_ENV=production
APP_DEBUG=false
APP_URL=https://${PANEL_DOMAIN}
LARAPANEL_VERSION=0.1.0

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${PANEL_USER}
DB_PASSWORD=${DB_PASSWORD}

SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

BROADCAST_CONNECTION=log
ENV_EOF

info "Instalando dependencias PHP (sin dev)..."
sudo -u "$PANEL_USER" composer install --no-dev --optimize-autoloader --no-scripts --quiet

info "Instalando dependencias JS y compilando assets..."
sudo -u "$PANEL_USER" npm install --silent
sudo -u "$PANEL_USER" npm run build

info "Generando clave de aplicación..."
sudo -u "$PANEL_USER" php artisan key:generate --force
sudo -u "$PANEL_USER" php artisan package:discover --ansi

info "Ejecutando migraciones..."
sudo -u "$PANEL_USER" php artisan migrate --force

info "Creando enlace simbólico de storage..."
sudo -u "$PANEL_USER" php artisan storage:link 2>/dev/null || true

info "Optimizando para producción..."
sudo -u "$PANEL_USER" php artisan config:cache
sudo -u "$PANEL_USER" php artisan route:cache
sudo -u "$PANEL_USER" php artisan view:cache
sudo -u "$PANEL_USER" php artisan event:cache

# Configurar permisos finales
chmod -R 775 "${INSTALL_DIR}/storage"
chmod -R 775 "${INSTALL_DIR}/bootstrap/cache"
chown -R "${PANEL_USER}:www-data" "${INSTALL_DIR}/storage"
chown -R "${PANEL_USER}:www-data" "${INSTALL_DIR}/bootstrap/cache"

success "Aplicación Laravel configurada."

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 9 — CREAR ADMIN POR CONSOLA
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 9 — Creando usuario administrador"

HASHED_PASSWORD=$(sudo -u "$PANEL_USER" php -r "echo password_hash('${ADMIN_PASSWORD}', PASSWORD_BCRYPT, ['cost' => 12]);")

sudo -u "$PANEL_USER" php artisan tinker --no-interaction <<TINKER_EOF 2>/dev/null || true
\$user = \App\Models\User::updateOrCreate(
    ['email' => '${ADMIN_EMAIL}'],
    [
        'name'     => 'Administrador',
        'password' => bcrypt('${ADMIN_PASSWORD}'),
    ]
);
\$user->assignRole('admin');
echo "Admin creado: " . \$user->email;
TINKER_EOF

success "Usuario administrador creado: ${ADMIN_EMAIL}"

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 10 — SUDOERS
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 10 — Configurando permisos sudo del sistema (sudoers)"

cat > /etc/sudoers.d/larapanel <<SUDOERS_EOF
# LaraPanel — Permisos de sistema para www-data (PHP-FPM worker)
Defaults:www-data !requiretty

# Nginx
www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart nginx
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start nginx
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop nginx

# PHP-FPM (cualquier versión)
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart php*-fpm
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload php*-fpm
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start php*-fpm
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop php*-fpm

# MySQL
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart mysql
www-data ALL=(ALL) NOPASSWD: /usr/bin/mysql

# Fail2ban
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart fail2ban
www-data ALL=(ALL) NOPASSWD: /usr/bin/fail2ban-client

# Firewall
www-data ALL=(ALL) NOPASSWD: /usr/sbin/ufw
www-data ALL=(ALL) NOPASSWD: /sbin/iptables

# Filesystem
www-data ALL=(ALL) NOPASSWD: /bin/chmod
www-data ALL=(ALL) NOPASSWD: /bin/chown
www-data ALL=(ALL) NOPASSWD: /bin/mkdir
www-data ALL=(ALL) NOPASSWD: /bin/rm
www-data ALL=(ALL) NOPASSWD: /bin/mv
www-data ALL=(ALL) NOPASSWD: /bin/cp
www-data ALL=(ALL) NOPASSWD: /bin/ln

# Logs y archivos
www-data ALL=(ALL) NOPASSWD: /usr/bin/tail
www-data ALL=(ALL) NOPASSWD: /usr/bin/truncate
www-data ALL=(ALL) NOPASSWD: /usr/bin/zip
www-data ALL=(ALL) NOPASSWD: /usr/bin/unzip

# SSL
www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot
www-data ALL=(ALL) NOPASSWD: /root/.acme.sh/acme.sh

# Docker
www-data ALL=(ALL) NOPASSWD: /usr/bin/docker

# ClamAV (Antivirus)
www-data ALL=(ALL) NOPASSWD: /usr/bin/clamscan
www-data ALL=(ALL) NOPASSWD: /usr/bin/clamdscan
www-data ALL=(ALL) NOPASSWD: /usr/bin/freshclam

# Rspamd (Antispam)
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart rspamd
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload rspamd
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start rspamd
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop rspamd
www-data ALL=(ALL) NOPASSWD: /usr/bin/rspamadm
SUDOERS_EOF

chmod 440 /etc/sudoers.d/larapanel
visudo -c && success "Sudoers configurado correctamente." || error "Error de sintaxis en sudoers. Revisa /etc/sudoers.d/larapanel"

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 11 — NGINX VIRTUAL HOST
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 11 — Configurando Nginx para el panel"

cat > "/etc/nginx/sites-available/larapanel" <<NGINX_EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${PANEL_DOMAIN};
    root ${INSTALL_DIR}/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # WebSockets Livewire/Reverb
    location /app {
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_pass http://127.0.0.1:8080;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 100M;

    access_log /var/log/nginx/larapanel.access.log;
    error_log  /var/log/nginx/larapanel.error.log;
}
NGINX_EOF

# Eliminar default y habilitar LaraPanel
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/larapanel /etc/nginx/sites-enabled/larapanel

nginx -t && systemctl reload nginx
success "Nginx configurado para ${PANEL_DOMAIN}."

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 12 — SSL (LET'S ENCRYPT)
# ══════════════════════════════════════════════════════════════════════════════
if [[ "${INSTALL_SSL,,}" == "s" ]]; then
    step "Fase 12 — Instalando SSL con Let's Encrypt"

    apt-get install -y -qq certbot python3-certbot-nginx

    if certbot --nginx \
        -d "$PANEL_DOMAIN" \
        --email "$ADMIN_EMAIL" \
        --agree-tos \
        --non-interactive \
        --redirect; then
        success "SSL instalado correctamente para ${PANEL_DOMAIN}."
    else
        warn "No se pudo instalar SSL automáticamente."
        warn "Asegúrate de que el DNS de '${PANEL_DOMAIN}' apunta a este servidor."
        warn "Puedes instalarlo luego con: certbot --nginx -d ${PANEL_DOMAIN}"
    fi
else
    info "SSL omitido por elección del usuario."
fi

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 13 — SUPERVISOR (QUEUE WORKERS)
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 13 — Configurando Supervisor (Queue Workers)"

cat > /etc/supervisor/conf.d/larapanel.conf <<SUP_EOF
[program:larapanel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${INSTALL_DIR}/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${PANEL_USER}
numprocs=2
redirect_stderr=true
stdout_logfile=${INSTALL_DIR}/storage/logs/worker.log
stopwaitsecs=3600

[program:larapanel-scheduler]
process_name=%(program_name)s
command=/bin/bash -c 'while true; do php ${INSTALL_DIR}/artisan schedule:run --no-interaction >> /dev/null 2>&1; sleep 60; done'
autostart=true
autorestart=true
user=${PANEL_USER}
redirect_stderr=true
stdout_logfile=${INSTALL_DIR}/storage/logs/scheduler.log
SUP_EOF

systemctl enable supervisor
systemctl start supervisor
supervisorctl reread
supervisorctl update
supervisorctl start larapanel-worker:* 2>/dev/null || true
success "Supervisor configurado. 2 workers de cola activos."

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 14 — FIREWALL (UFW)
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 14 — Configurando Firewall (UFW)"

ufw --force reset 2>/dev/null
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow http
ufw allow https
ufw allow 8080/tcp comment 'LaraPanel Reverb WebSockets'
# Rspamd API — solo acceso local (no abrir al exterior)
# ufw allow 11334/tcp comment 'Rspamd API (local only)'
echo "y" | ufw enable
success "Firewall UFW configurado."

# ══════════════════════════════════════════════════════════════════════════════
#   FASE 15 — ACME.SH PARA CERTBOT INTERNO DEL PANEL
# ══════════════════════════════════════════════════════════════════════════════
step "Fase 15 — Instalando acme.sh para gestión SSL interna del panel"

if [[ ! -f "/root/.acme.sh/acme.sh" ]]; then
    curl -fsSL https://get.acme.sh | bash -s "email=${ADMIN_EMAIL}" --no-profile 2>/dev/null
    success "acme.sh instalado en /root/.acme.sh/"
else
    info "acme.sh ya está instalado."
fi

# Enlace simbólico para que www-data pueda ejecutarlo via sudo
ln -sf /root/.acme.sh/acme.sh /usr/local/bin/acme.sh 2>/dev/null || true

# ══════════════════════════════════════════════════════════════════════════════
#   RESUMEN FINAL
# ══════════════════════════════════════════════════════════════════════════════
echo ""
echo -e "${BOLD}${GREEN}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║          ✅  LaraPanel instalado exitosamente            ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BOLD}  Datos de acceso:${NC}"
if [[ "${INSTALL_SSL,,}" == "s" ]]; then
    echo -e "  • URL:        ${GREEN}https://${PANEL_DOMAIN}${NC}"
else
    echo -e "  • URL:        ${YELLOW}http://${PANEL_DOMAIN}${NC} (sin SSL)"
fi
echo -e "  • Email:      ${GREEN}${ADMIN_EMAIL}${NC}"
echo -e "  • Contraseña: ${GREEN}[la que ingresaste]${NC}"
echo ""
echo -e "${BOLD}  Rutas importantes:${NC}"
echo -e "  • Aplicación: ${CYAN}${INSTALL_DIR}${NC}"
echo -e "  • Logs Nginx: ${CYAN}/var/log/nginx/larapanel.error.log${NC}"
echo -e "  • Logs App:   ${CYAN}${INSTALL_DIR}/storage/logs/laravel.log${NC}"
echo -e "  • Sudoers:    ${CYAN}/etc/sudoers.d/larapanel${NC}"
echo -e "  • Cuarentena: ${CYAN}/var/larapanel/quarantine${NC} (ClamAV)"
echo -e "  • Rspamd API: ${CYAN}http://127.0.0.1:11334${NC}"
echo ""
echo -e "${BOLD}  Servicios instalados:${NC}"
echo -e "  ✓ Nginx + PHP ${PHP_VERSION} + MySQL 8"
echo -e "  ✓ Docker Engine + Docker Compose"
echo -e "  ✓ ClamAV (antivirus) + cuarentena automática"
echo -e "  ✓ Rspamd (antispam) + Redis + Bayes"
echo -e "  ✓ Fail2ban + UFW"
echo -e "  ✓ Supervisor (queue workers)"
echo -e "  ✓ acme.sh + Certbot (SSL)"
echo ""
echo -e "${BOLD}  Comandos útiles:${NC}"
echo -e "  • Ver workers:         ${CYAN}supervisorctl status${NC}"
echo -e "  • Reiniciar workers:   ${CYAN}supervisorctl restart larapanel-worker:*${NC}"
echo -e "  • Ver logs en vivo:    ${CYAN}tail -f ${INSTALL_DIR}/storage/logs/laravel.log${NC}"
echo -e "  • Limpiar caché:       ${CYAN}cd ${INSTALL_DIR} && php artisan optimize:clear${NC}"
echo ""

# Guardar resumen en archivo
cat > "${INSTALL_DIR}/INSTALL_INFO.txt" <<INFO_EOF
LaraPanel — Instalación completada $(date)
==========================================
URL:          https://${PANEL_DOMAIN}
Admin Email:  ${ADMIN_EMAIL}
Directorio:   ${INSTALL_DIR}
DB:           ${DB_NAME} (usuario: ${PANEL_USER})
PHP Version:  ${PHP_VERSION}
Node Version: $(node -v)
INFO_EOF

chmod 600 "${INSTALL_DIR}/INSTALL_INFO.txt"
success "Resumen guardado en ${INSTALL_DIR}/INSTALL_INFO.txt"
echo ""
