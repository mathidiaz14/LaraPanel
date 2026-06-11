#!/bin/bash

# ==============================================================================
# LaraPanel — Motor de Correo (Postfix + Dovecot) Instalador Independiente
# Este script instala el servidor IMAP/POP3/SMTP y lo conecta a la DB de LaraPanel.
# ==============================================================================

if [ "$EUID" -ne 0 ]; then
  echo "Por favor, ejecuta este script como root (sudo)."
  exit 1
fi

INSTALL_DIR="/var/www/panel"

# Cargar colores y utilidades de log si existen, o definirlos básico
BOLD='\033[1m'
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

info()    { echo -e "${CYAN}[INFO] ${NC} $1"; }
success() { echo -e "${GREEN}[OK]   ${NC} $1"; }
warn()    { echo -e "${YELLOW}[WARN] ${NC} $1"; }
error()   { echo -e "${RED}[ERR]  ${NC} $1"; exit 1; }
step()    { echo -e "\n${BOLD}${CYAN}▶ $1${NC}"; }

# Extraer credenciales de base de datos de Laravel .env
if [ ! -f "$INSTALL_DIR/.env" ]; then
    error "No se encontró el archivo .env de LaraPanel en $INSTALL_DIR"
fi

DB_DATABASE=$(grep '^DB_DATABASE=' "$INSTALL_DIR/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")
DB_USERNAME=$(grep '^DB_USERNAME=' "$INSTALL_DIR/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")
DB_PASSWORD=$(grep '^DB_PASSWORD=' "$INSTALL_DIR/.env" | cut -d '=' -f2 | tr -d '"' | tr -d "'")

if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ]; then
    error "No se pudieron extraer las credenciales de base de datos desde .env"
fi

# ==============================================================================
# FASE 1 — Instalación de Paquetes
# ==============================================================================
step "Instalando paquetes de Postfix y Dovecot..."

# Configurar debconf para que Postfix no haga preguntas interactivas
debconf-set-selections <<< "postfix postfix/mailname string $(hostname -f)"
debconf-set-selections <<< "postfix postfix/main_mailer_type string 'Internet Site'"

apt-get update -qq
apt-get install -y -qq postfix postfix-mysql dovecot-core dovecot-imapd dovecot-pop3d dovecot-lmtpd dovecot-mysql

# ==============================================================================
# FASE 2 — Usuario VMAIL y Directorio
# ==============================================================================
step "Configurando usuario de sistema 'vmail' y directorios físicos..."

if ! id -u vmail > /dev/null 2>&1; then
    groupadd -g 5000 vmail
    useradd -g vmail -u 5000 vmail -d /var/vmail -m -s /usr/sbin/nologin
else
    info "El usuario vmail ya existe."
fi

mkdir -p /var/vmail
chown -R vmail:vmail /var/vmail
chmod -R 770 /var/vmail

# ==============================================================================
# FASE 3 — Configuración de Dovecot (IMAP / POP3)
# ==============================================================================
step "Configurando Dovecot e integrando base de datos MySQL..."

# Respaldar conf original
if [ ! -f "/etc/dovecot/dovecot.conf.bak" ]; then
    cp -r /etc/dovecot /etc/dovecot.bak
fi

# 1. Configuración principal de Dovecot (Habilitar protocolos)
sed -i 's/#protocols = imap pop3 lmtp submission/protocols = imap pop3 lmtp/g' /etc/dovecot/dovecot.conf

# 2. Configurar 10-mail.conf (Ruta del correo)
cat > /etc/dovecot/conf.d/10-mail.conf <<EOF
mail_location = maildir:/var/vmail/%d/%n
namespace inbox {
  inbox = yes
}
mail_privileged_group = vmail
mail_uid = 5000
mail_gid = 5000
EOF

# 3. Configurar 10-auth.conf (Mecanismos y base de datos)
cat > /etc/dovecot/conf.d/10-auth.conf <<EOF
disable_plaintext_auth = no
auth_mechanisms = plain login
#!include auth-system.conf.ext
!include auth-sql.conf.ext
EOF

# 4. Configurar auth-sql.conf.ext
cat > /etc/dovecot/conf.d/auth-sql.conf.ext <<EOF
passdb {
  driver = sql
  args = /etc/dovecot/dovecot-sql.conf.ext
}
userdb {
  driver = static
  args = uid=vmail gid=vmail home=/var/vmail/%d/%n
}
EOF

# 5. Configurar dovecot-sql.conf.ext (La conexión a LaraPanel)
cat > /etc/dovecot/dovecot-sql.conf.ext <<EOF
driver = mysql
connect = host=127.0.0.1 dbname=${DB_DATABASE} user=${DB_USERNAME} password=${DB_PASSWORD}
default_pass_scheme = BLF-CRYPT
# Query para obtener la contraseña hash usando el email
password_query = SELECT email as user, password_hash as password FROM email_accounts WHERE email = '%u' AND is_active = 1;
EOF

chown root:root /etc/dovecot/dovecot-sql.conf.ext
chmod 600 /etc/dovecot/dovecot-sql.conf.ext

# 6. Configurar 10-master.conf (Sockets para Postfix)
cat > /etc/dovecot/conf.d/10-master.conf <<EOF
service imap-login {
  inet_listener imap {
    port = 143
  }
  inet_listener imaps {
    port = 993
    ssl = yes
  }
}
service pop3-login {
  inet_listener pop3 {
    port = 110
  }
  inet_listener pop3s {
    port = 995
    ssl = yes
  }
}
service lmtp {
  unix_listener /var/spool/postfix/private/dovecot-lmtp {
    mode = 0600
    user = postfix
    group = postfix
  }
}
service auth {
  # Autenticador SMTP para Postfix (SASL)
  unix_listener /var/spool/postfix/private/auth {
    mode = 0666
    user = postfix
    group = postfix
  }
  unix_listener auth-userdb {
    mode = 0600
    user = vmail
  }
  user = dovecot
}
service auth-worker {
  user = vmail
}
EOF

# SSL básico por si acaso, idealmente esto se pisará por dominios (SNI) o se usará proxy
cat > /etc/dovecot/conf.d/10-ssl.conf <<EOF
ssl = yes
ssl_cert = </etc/ssl/certs/ssl-cert-snakeoil.pem
ssl_key = </etc/ssl/private/ssl-cert-snakeoil.key
ssl_min_protocol = TLSv1.2
ssl_cipher_list = HIGH:!aNULL:!kRSA:!PSK:!SRP:!MD5:!RC4
EOF

# ==============================================================================
# FASE 4 — Configuración de Postfix (SMTP)
# ==============================================================================
step "Configurando Postfix e integrando mapas SQL..."

# Respaldar conf original
if [ ! -f "/etc/postfix/main.cf.bak" ]; then
    cp /etc/postfix/main.cf /etc/postfix/main.cf.bak
fi

# 1. Configurar mapas SQL para Postfix
cat > /etc/postfix/mysql-virtual-mailbox-domains.cf <<EOF
user = ${DB_USERNAME}
password = ${DB_PASSWORD}
hosts = 127.0.0.1
dbname = ${DB_DATABASE}
query = SELECT 1 FROM domains WHERE name='%s' AND is_active=1
EOF

cat > /etc/postfix/mysql-virtual-mailbox-maps.cf <<EOF
user = ${DB_USERNAME}
password = ${DB_PASSWORD}
hosts = 127.0.0.1
dbname = ${DB_DATABASE}
query = SELECT 1 FROM email_accounts WHERE email='%s' AND is_active=1
EOF

cat > /etc/postfix/mysql-virtual-alias-maps.cf <<EOF
user = ${DB_USERNAME}
password = ${DB_PASSWORD}
hosts = 127.0.0.1
dbname = ${DB_DATABASE}
# Como los forwarders están en formato JSON array en LaraPanel, extraemos los correos
# y limpiamos las comillas y corchetes para que Postfix lea un listado CSV.
query = SELECT REPLACE(REPLACE(REPLACE(forwarders, ']', ''), '[', ''), '"', '') FROM email_accounts WHERE email='%s' AND is_active=1 AND forwarders IS NOT NULL AND JSON_LENGTH(forwarders) > 0
EOF

chmod 600 /etc/postfix/mysql-virtual-*.cf

# 2. Configurar main.cf
postconf -e "myhostname = $(hostname -f)"
postconf -e "smtpd_banner = \$myhostname ESMTP LaraPanel"
postconf -e "biff = no"
postconf -e "append_dot_mydomain = no"
postconf -e "readme_directory = no"

# Virtual Domains & Mailboxes (MySQL)
postconf -e "virtual_mailbox_domains = proxy:mysql:/etc/postfix/mysql-virtual-mailbox-domains.cf"
postconf -e "virtual_mailbox_maps = proxy:mysql:/etc/postfix/mysql-virtual-mailbox-maps.cf"
postconf -e "virtual_alias_maps = proxy:mysql:/etc/postfix/mysql-virtual-alias-maps.cf"
postconf -e "virtual_transport = lmtp:unix:private/dovecot-lmtp"

# Autenticación SASL (Dovecot)
postconf -e "smtpd_sasl_type = dovecot"
postconf -e "smtpd_sasl_path = private/auth"
postconf -e "smtpd_sasl_auth_enable = yes"
postconf -e "smtpd_recipient_restrictions = permit_mynetworks, permit_sasl_authenticated, reject_unauth_destination"

# SSL/TLS (Básico)
postconf -e "smtpd_tls_cert_file=/etc/ssl/certs/ssl-cert-snakeoil.pem"
postconf -e "smtpd_tls_key_file=/etc/ssl/private/ssl-cert-snakeoil.key"
postconf -e "smtpd_tls_security_level=may"
postconf -e "smtp_tls_security_level=may"

# Integración con Rspamd (si existe)
if systemctl is-active --quiet rspamd; then
    info "Rspamd detectado. Configurando Postfix Milter..."
    postconf -e "smtpd_milters = inet:127.0.0.1:11332"
    postconf -e "non_smtpd_milters = inet:127.0.0.1:11332"
    postconf -e "milter_protocol = 6"
    postconf -e "milter_mail_macros = i {mail_addr} {client_addr} {client_name} {auth_authen}"
    postconf -e "milter_default_action = accept"
fi

# 3. Configurar master.cf para SMTP seguro (puertos 465 y 587)
# Habilitar submission (587)
sed -i '/^#submission/s/^#//g' /etc/postfix/master.cf
sed -i '/^submission/a \  -o syslog_name=postfix/submission\n  -o smtpd_tls_security_level=encrypt\n  -o smtpd_sasl_auth_enable=yes\n  -o smtpd_relay_restrictions=permit_sasl_authenticated,reject' /etc/postfix/master.cf

# Habilitar smtps (465)
sed -i '/^#smtps/s/^#//g' /etc/postfix/master.cf
sed -i '/^smtps/a \  -o syslog_name=postfix/smtps\n  -o smtpd_tls_wrappermode=yes\n  -o smtpd_sasl_auth_enable=yes\n  -o smtpd_relay_restrictions=permit_sasl_authenticated,reject' /etc/postfix/master.cf

# ==============================================================================
# FASE 5 — Reinicio de Servicios
# ==============================================================================
step "Reiniciando servicios de correo..."

systemctl restart dovecot
systemctl enable dovecot

systemctl restart postfix
systemctl enable postfix

success "¡Instalación y configuración del Servidor de Correo (Postfix + Dovecot) completada!"
echo "Ahora puedes acceder a webmail y conectarte a tus casillas alojadas en LaraPanel."
