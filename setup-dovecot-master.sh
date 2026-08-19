#!/bin/bash

# ==============================================================================
# LaraPanel — Configurar Dovecot Master User (Para Auto-login en Webmail)
# Usa el master user "larapanel" y guarda la clave en
# /etc/roundcube/larapanel_master_pwd (consistente con install.sh).
# ==============================================================================

if [ "$EUID" -ne 0 ]; then
  echo "Por favor, ejecuta este script como root (sudo)."
  exit 1
fi

MASTER_PWD_FILE="/etc/dovecot/master-users"
ROUNDCUBE_PWD_FILE="/etc/roundcube/larapanel_master_pwd"

if ! grep -q 'master = yes' /etc/dovecot/conf.d/10-auth.conf; then
    echo "Configuring Dovecot Master User..."

    # Generate random master password
    MASTER_PASS=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 32)

    echo "larapanel:{PLAIN}$MASTER_PASS" > "$MASTER_PWD_FILE"
    chmod 644 "$MASTER_PWD_FILE"
    chown root:root "$MASTER_PWD_FILE"

    cat << 'EOF' >> /etc/dovecot/conf.d/10-auth.conf
auth_master_user_separator = *
passdb {
  driver = passwd-file
  args = /etc/dovecot/master-users
  master = yes
  pass = yes
}
EOF
    systemctl restart dovecot

    # Save the master password so Roundcube can use it
    echo "$MASTER_PASS" > "$ROUNDCUBE_PWD_FILE"
    chmod 640 "$ROUNDCUBE_PWD_FILE"
    chown root:www-data "$ROUNDCUBE_PWD_FILE"
    echo "Dovecot Master User configurado con éxito."
else
    # El master user ya estaba configurado; solo aseguramos que dovecot
    # pueda leer el archivo (euid=dovecot) y que exista la clave para Roundcube.
    if [ -f "$MASTER_PWD_FILE" ]; then
        chmod 644 "$MASTER_PWD_FILE"
        echo "Dovecot Master User ya estaba configurado."
    else
        echo "ERROR: $MASTER_PWD_FILE no existe. Ejecuta de nuevo con permisos." >&2
        exit 1
    fi
fi