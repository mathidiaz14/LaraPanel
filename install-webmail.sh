            #!/usr/bin/env bash
# ==============================================================================
#  LaraPanel — Instalador y Configurado Automático de Webmail (Roundcube)
#  Uso: sudo bash install-webmail.sh
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

info()    { echo -e "${BLUE}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*" >&2; exit 1; }

# ── Verificaciones iniciales ──────────────────────────────────────────────────
if [[ $EUID -ne 0 ]]; then
    error "Este script debe ejecutarse como root: sudo bash install-webmail.sh"
fi

OS_ID=$(grep -oP '(?<=^ID=).+' /etc/os-release | tr -d '"')
if [[ "$OS_ID" != "ubuntu" ]]; then
    error "Este instalador solo soporta Ubuntu. Sistema detectado: $OS_ID"
fi

# ══════════════════════════════════════════════════════════════════════════════
#   INSTALAR ROUNDCUBE Y DEPENDENCIAS
# ══════════════════════════════════════════════════════════════════════════════
info "Fase 1 — Instalando Roundcube Webmail y SQLite3..."

export DEBIAN_FRONTEND=noninteractive

# Pre-configurar respuestas para la instalación no interactiva de Roundcube
# Esto evita que salgan ventanas emergentes preguntando configuraciones de DB
debconf-set-selections <<< "roundcube-core roundcube/dbconfig-install boolean true"
debconf-set-selections <<< "roundcube-core roundcube/database-type select sqlite3"

apt-get update -qq
apt-get install -y -qq roundcube roundcube-core roundcube-sqlite3 roundcube-plugins sqlite3 php-intl php-mail-mime

success "Roundcube instalado."

# ══════════════════════════════════════════════════════════════════════════════
#   CONFIGURAR ROUNDCUBE PARA CONECTAR AL SERVIDOR DE CORREO LOCAL
# ══════════════════════════════════════════════════════════════════════════════
info "Fase 2 — Configurando archivos de Roundcube..."

ROUNDCUBE_CONFIG="/etc/roundcube/config.inc.php"

# Respaldar configuración anterior
if [ -f "$ROUNDCUBE_CONFIG" ] && [ ! -f "${ROUNDCUBE_CONFIG}.bak" ]; then
    cp "$ROUNDCUBE_CONFIG" "${ROUNDCUBE_CONFIG}.bak"
fi

# Detectar ruta de la base de datos de Roundcube
# Normalmente es /var/lib/dbconfig-common/sqlite3/roundcube/roundcube
DB_PATH="/var/lib/dbconfig-common/sqlite3/roundcube/roundcube"
if [ ! -f "$DB_PATH" ]; then
    mkdir -p "$(dirname "$DB_PATH")"
    touch "$DB_PATH"
    chown -R www-data:www-data "$(dirname "$DB_PATH")"
    chmod -R 770 "$(dirname "$DB_PATH")"
fi

# Escribir configuración optimizada para LaraPanel
cat > "$ROUNDCUBE_CONFIG" <<CONF_EOF
<?php
// Configuración personalizada de Roundcube para LaraPanel
\$config = array();

// Base de Datos SQLite
\$config['db_dsnw'] = 'sqlite:///${DB_PATH}?mode=0660';

// Configuración de Servidores Locales de Correo (IMAP y SMTP)
// Usamos localhost ya que Roundcube está en el mismo servidor
\$config['imap_host'] = 'localhost:143';
\$config['smtp_host'] = 'localhost:25';

// Forzar el uso del dominio de correo completo como usuario
\$config['username_domain'] = '';

// Nombre del producto y marca
\$config['product_name'] = 'LaraPanel Webmail';

// Idioma por defecto en Español
\$config['language'] = 'es_ES';

// Plugins útiles
\$config['plugins'] = array(
    'archive',
    'zipdownload',
    'managesieve',
    'password'
);

// Permitir crear casillas y contraseñas de forma limpia
\$config['skin'] = 'elastic';
CONF_EOF

# Asegurar permisos correctos para que Nginx / PHP-FPM puedan leer la configuración
chown -R root:www-data /etc/roundcube
chmod 640 "$ROUNDCUBE_CONFIG"

success "Configuración de Roundcube guardada."

# ══════════════════════════════════════════════════════════════════════════════
#   DETECTAR VERSIÓN ACTIVA DE PHP-FPM
# ══════════════════════════════════════════════════════════════════════════════
info "Fase 3 — Detectando socket PHP-FPM..."

PHP_SOCKET=""
for V in 8.3 8.2 8.1; do
    if [ -S "/var/run/php/php${V}-fpm.sock" ]; then
        PHP_SOCKET="/var/run/php/php${V}-fpm.sock"
        info "Detectado PHP-FPM socket: $PHP_SOCKET"
        break
    fi
done

if [ -z "$PHP_SOCKET" ]; then
    # Fallback si no detecta la ruta estándar
    PHP_SOCKET="/var/run/php/php-fpm.sock"
    warn "No se detectó un socket PHP-FPM activo estándar. Usando fallback: $PHP_SOCKET"
fi

# ══════════════════════════════════════════════════════════════════════════════
#   CONFIGURAR NGINX WILDCARD PARA WEBMAIL
# ══════════════════════════════════════════════════════════════════════════════
info "Fase 4 — Configurando Nginx comodín para webmail.* ..."

NGINX_CONF="/etc/nginx/sites-available/larapanel_webmail"
NGINX_ENABLED="/etc/nginx/sites-enabled/larapanel_webmail"

cat > "$NGINX_CONF" <<CONF_EOF
# ==============================================================================
# LaraPanel Wildcard Webmail Server Block
# Redirecciona automáticamente webmail.cualquierdominio.com a Roundcube
# ==============================================================================

server {
    listen 80;
    listen [::]:80;
    
    # Expresión regular para capturar cualquier dominio con el prefijo webmail.
    server_name ~^webmail\.(?<domain_name>.+)$;

    # Ruta oficial de Roundcube en Ubuntu
    root /usr/share/roundcube;
    index index.php index.html index.htm;

    access_log /var/log/nginx/webmail.access.log;
    error_log /var/log/nginx/webmail.error.log;

    # Encabezados de Seguridad
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    # Procesar archivos PHP con el socket activo detectado
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_SOCKET};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # Denegar archivos ocultos
    location ~ /\. {
        deny all;
    }

    # Caché de archivos estáticos
    location ~* \.(jpg|jpeg|gif|css|png|js|ico|html|xml|txt)$ {
        expires max;
    }
}
CONF_EOF

# Habilitar el sitio en Nginx
rm -f "$NGINX_ENABLED"
ln -sf "$NGINX_CONF" "$NGINX_ENABLED"

# Probar Nginx y reiniciar
if nginx -t; then
    systemctl restart nginx
    success "Nginx configurado y reiniciado con éxito."
else
    error "Error al configurar Nginx. Revisa el archivo $NGINX_CONF"
fi

echo -e "\n${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BOLD}${GREEN}  ¡Instalación de Webmail (Roundcube) completada con éxito!${NC}"
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"
echo -e "Cómo funciona:"
echo -e "1. Cualquier dominio creado en LaraPanel responderá a ${BOLD}webmail.tudominio.com${NC}"
echo -e "   siempre y cuando el DNS apunte a tu VPS."
echo -e "2. El usuario podrá acceder con su email completo (ej: ${CYAN}info@dominio.com${NC})"
echo -e "   y la contraseña que creaste en el panel."
echo -e "3. Roundcube se conecta localmente al Dovecot IMAP de tu VPS."
echo ""
echo -e "${YELLOW}Nota sobre el DNS:${NC} Asegúrate de tener un registro comodín (${BOLD}*${NC}) o un registro específico"
echo -e "para ${BOLD}webmail${NC} (Tipo A, apuntando a tu VPS) en tu zona DNS (por ejemplo en tu Cloudflare)."
