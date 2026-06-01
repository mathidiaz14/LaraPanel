#!/usr/bin/env bash
# ==============================================================================
#  LaraPanel — Instalador y Configurado Automático de DNS (PowerDNS)
#  Uso: sudo bash install-dns.sh
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
    error "Este script debe ejecutarse como root: sudo bash install-dns.sh"
fi

OS_ID=$(grep -oP '(?<=^ID=).+' /etc/os-release | tr -d '"')
if [[ "$OS_ID" != "ubuntu" ]]; then
    error "Este instalador solo soporta Ubuntu. Sistema detectado: $OS_ID"
fi

# Intentar detectar la clave de la API de PowerDNS desde el archivo .env del panel
PANEL_ENV="/var/www/panel/.env"
PDNS_API_KEY="larapanel_pdns_secret"

if [ -f "$PANEL_ENV" ]; then
    PDNS_KEY_DETECTED=$(grep -oP '(?<=^PDNS_API_KEY=).+' "$PANEL_ENV" || true)
    if [ -n "$PDNS_KEY_DETECTED" ]; then
        PDNS_API_KEY="$PDNS_KEY_DETECTED"
        info "Clave de API de PowerDNS detectada desde el .env del panel."
    fi
fi

# ══════════════════════════════════════════════════════════════════════════════
#   DESACTIVAR STUB LISTENER DE SYSTEMD-RESOLVED (PUERTO 53)
# ══════════════════════════════════════════════════════════════════════════════
info "Fase 1 — Desactivando systemd-resolved en el puerto 53 para evitar conflictos..."

if [ -f /etc/systemd/resolved.conf ]; then
    # Habilitar DNSStubListener=no en resolved.conf
    if grep -q "^#DNSStubListener=" /etc/systemd/resolved.conf; then
        sed -i 's/^#DNSStubListener=.*/DNSStubListener=no/' /etc/systemd/resolved.conf
    elif grep -q "^DNSStubListener=" /etc/systemd/resolved.conf; then
        sed -i 's/^DNSStubListener=.*/DNSStubListener=no/' /etc/systemd/resolved.conf
    else
        echo "DNSStubListener=no" >> /etc/systemd/resolved.conf
    fi
    
    # Configurar DNS secundario para el propio VPS
    if ! grep -q "^DNS=" /etc/systemd/resolved.conf; then
        echo "DNS=1.1.1.1 8.8.8.8" >> /etc/systemd/resolved.conf
    fi

    # Actualizar enlace simbólico de resolv.conf
    rm -f /etc/resolv.conf
    ln -sf /run/systemd/resolve/resolv.conf /etc/resolv.conf
    
    systemctl restart systemd-resolved
    success "Systemd-resolved configurado correctamente."
else
    warn "No se encontró resolved.conf. Asegúrate de que el puerto 53 esté libre."
fi

# ══════════════════════════════════════════════════════════════════════════════
#   INSTALAR POWERDNS Y SU BACKEND SQLITE
# ══════════════════════════════════════════════════════════════════════════════
info "Fase 2 — Instalando PowerDNS y Backend SQLite3..."

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq pdns-server pdns-backend-sqlite3 sqlite3

# Detener el servicio durante la configuración
systemctl stop pdns || true

success "Paquetes de PowerDNS instalados."

# ══════════════════════════════════════════════════════════════════════════════
#   INICIALIZAR BASE DE DATOS SQLITE3
# ══════════════════════════════════════════════════════════════════════════════
info "Fase 3 — Inicializando Base de Datos SQLite3..."

PDNS_DB_DIR="/var/spool/powerdns"
PDNS_DB_FILE="${PDNS_DB_DIR}/pdns.sqlite3"

mkdir -p "$PDNS_DB_DIR"

# Crear y estructurar la base de datos si no existe
if [ ! -f "$PDNS_DB_FILE" ]; then
    sqlite3 "$PDNS_DB_FILE" <<SQL_EOF
CREATE TABLE domains (
  id                    INTEGER PRIMARY KEY,
  name                  VARCHAR(255) NOT NULL COLLATE NOCASE,
  master                VARCHAR(128) DEFAULT NULL,
  last_check            INTEGER DEFAULT NULL,
  type                  VARCHAR(6) NOT NULL,
  notified_serial       INTEGER DEFAULT NULL,
  account               VARCHAR(40) DEFAULT NULL
);
CREATE UNIQUE INDEX name_index ON domains(name);

CREATE TABLE records (
  id                    INTEGER PRIMARY KEY,
  domain_id             INTEGER DEFAULT NULL,
  name                  VARCHAR(255) DEFAULT NULL COLLATE NOCASE,
  type                  VARCHAR(10) DEFAULT NULL,
  content               VARCHAR(65535) DEFAULT NULL,
  ttl                   INTEGER DEFAULT NULL,
  prio                  INTEGER DEFAULT NULL,
  disabled              BOOLEAN DEFAULT 0,
  ordername             VARCHAR(255) COLLATE NOCASE,
  auth                  BOOLEAN DEFAULT 1,
  FOREIGN KEY(domain_id) REFERENCES domains(id) ON DELETE CASCADE
);
CREATE INDEX rec_name_index ON records(name);
CREATE INDEX nametype_index ON records(name,type);
CREATE INDEX domain_id ON records(domain_id);
CREATE INDEX ordername ON records(ordername);

CREATE TABLE supermasters (
  ip                    VARCHAR(64) NOT NULL,
  nameserver            VARCHAR(255) NOT NULL COLLATE NOCASE,
  account               VARCHAR(40) DEFAULT NULL
);
CREATE UNIQUE INDEX ip_nameserver_idx ON supermasters(ip, nameserver);

CREATE TABLE comments (
  id                    INTEGER PRIMARY KEY,
  domain_id             INTEGER NOT NULL,
  name                  VARCHAR(255) NOT NULL COLLATE NOCASE,
  type                  VARCHAR(10) NOT NULL,
  modified_at           INTEGER NOT NULL,
  account               VARCHAR(40) DEFAULT NULL,
  comment               VARCHAR(65535) NOT NULL,
  FOREIGN KEY(domain_id) REFERENCES domains(id) ON DELETE CASCADE
);
CREATE INDEX comments_domain_id_idx ON comments(domain_id);
CREATE INDEX comments_name_type_idx ON comments(name, type);
CREATE INDEX comments_order_idx ON comments(domain_id, modified_at);

CREATE TABLE domainmetadata (
 id                     INTEGER PRIMARY KEY,
 domain_id              INTEGER NOT NULL,
 kind                   VARCHAR(32) COLLATE NOCASE,
 content                TEXT,
 FOREIGN KEY(domain_id) REFERENCES domains(id) ON DELETE CASCADE
);
CREATE INDEX domainmetaidindex ON domainmetadata(domain_id);

CREATE TABLE cryptokeys (
 id                     INTEGER PRIMARY KEY,
 domain_id              INTEGER NOT NULL,
 flags                  INTEGER NOT NULL,
 active                 BOOLEAN,
 published              BOOLEAN DEFAULT 1,
 content                TEXT,
 FOREIGN KEY(domain_id) REFERENCES domains(id) ON DELETE CASCADE
);
CREATE INDEX domainidindex ON cryptokeys(domain_id);

CREATE TABLE tsigkeys (
 id                     INTEGER PRIMARY KEY,
 name                   VARCHAR(255) COLLATE NOCASE,
 algorithm              VARCHAR(50) COLLATE NOCASE,
 secret                 VARCHAR(255)
);
CREATE UNIQUE INDEX namealgoindex ON tsigkeys(name, algorithm);
SQL_EOF

    success "Base de datos SQLite3 inicializada."
else
    info "La base de datos SQLite3 ya existe. Omitiendo inicialización."
fi

# Ajustar permisos para que PowerDNS pueda leer y escribir en la BD
chown -R pdns:pdns "$PDNS_DB_DIR"
chmod 770 "$PDNS_DB_DIR"
chmod 660 "$PDNS_DB_FILE"

# ══════════════════════════════════════════════════════════════════════════════
#   CONFIGURAR POWERDNS
# ══════════════════════════════════════════════════════════════════════════════
info "Fase 4 — Configurando archivo pdns.conf..."

PDNS_CONF="/etc/powerdns/pdns.conf"

# Crear copia de seguridad de la configuración original
if [ ! -f "${PDNS_CONF}.bak" ]; then
    cp "$PDNS_CONF" "${PDNS_CONF}.bak"
fi

# Escribir configuración personalizada
cat > "$PDNS_CONF" <<CONF_EOF
# Configuración personalizada de PowerDNS para LaraPanel
launch=gsqlite3
gsqlite3-database=${PDNS_DB_FILE}

# Escuchar en todas las interfaces públicas para consultas DNS (puerto 53)
local-address=0.0.0.0, ::
local-port=53

# Configurar API Webserver interno para sincronización con LaraPanel
webserver=yes
webserver-address=127.0.0.1
webserver-port=8053
webserver-allow-from=127.0.0.1, ::1

api=yes
api-key=${PDNS_API_KEY}

# Configuración de Seguridad y Rendimiento
security-poll-suffix=
distributor-threads=2
receiver-threads=1
CONF_EOF

success "Archivo pdns.conf configurado correctamente."

# ══════════════════════════════════════════════════════════════════════════════
#   ARRANCAR Y HABILITAR SERVICIO
# ══════════════════════════════════════════════════════════════════════════════
info "Fase 5 — Iniciando el servicio PowerDNS..."

systemctl daemon-reload
systemctl enable pdns
systemctl start pdns

# Verificar si está respondiendo en el puerto 8053
if curl -s -H "X-API-Key: ${PDNS_API_KEY}" http://127.0.0.1:8053/api/v1/servers/localhost > /dev/null; then
    success "¡PowerDNS levantado correctamente! Conectado con éxito a la API."
else
    error "PowerDNS se inició pero la API en el puerto 8053 no responde."
fi

# ══════════════════════════════════════════════════════════════════════════════
#   CONFIGURAR FIREWALL (UFW)
# ══════════════════════════════════════════════════════════════════════════════
info "Fase 6 — Habilitando puerto 53 en el Firewall (UFW)..."

if command -v ufw &>/dev/null; then
    ufw allow 53/tcp comment 'LaraPanel DNS TCP' || true
    ufw allow 53/udp comment 'LaraPanel DNS UDP' || true
    ufw reload || true
    success "Puertos de Firewall abiertos (53/TCP y 53/UDP)."
else
    warn "UFW no está instalado. Asegúrate de abrir el puerto 53 (TCP/UDP) en tu firewall externo."
fi

echo -e "\n${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BOLD}${GREEN}  ¡PowerDNS se ha instalado y configurado correctamente!${NC}"
echo -e "  • Puerto de consultas públicas: ${CYAN}53 (TCP/UDP)${NC}"
echo -e "  • API interna de LaraPanel:      ${CYAN}127.0.0.1:8053${NC}"
echo -e "  • Clave de la API (API-Key):    ${CYAN}${PDNS_API_KEY}${NC}"
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"
echo -e "Siguientes pasos:"
echo -e "1. Asegúrate de habilitar la sincronización en tu panel en producción."
echo -e "   En tu archivo ${CYAN}/var/www/panel/.env${NC} pon: ${BOLD}PDNS_ENABLED=true${NC} y limpia la caché."
echo -e "2. Crea registros tipo A en Cloudflare para ${BOLD}ns1.mathiasdiaz.uy${NC} y ${BOLD}ns2.mathiasdiaz.uy${NC}"
echo -e "   apuntando a la IP de este VPS."
echo -e "3. En nic.com.uy delega tus nuevos dominios a esos dos Name Servers."
