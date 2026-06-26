#!/bin/bash

# ==============================================================================
# LaraPanel — Configurar Dovecot Master User (Para Auto-login en Webmail)
# ==============================================================================

if [ "$EUID" -ne 0 ]; then
  echo "Por favor, ejecuta este script como root (sudo)."
  exit 1
fi

if ! grep -q 'master = yes' /etc/dovecot/conf.d/10-auth.conf; then
    echo "Configuring Dovecot Master User..."
    
    # Generate random master password
    MASTER_PASS=$(head -c 16 /dev/urandom | xxd -p)
    
    echo "roundcube:{PLAIN}$MASTER_PASS" > /etc/dovecot/master-users
    chmod 600 /etc/dovecot/master-users
    chown root:root /etc/dovecot/master-users

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
    echo "$MASTER_PASS" > /etc/roundcube/master_pass
    chmod 644 /etc/roundcube/master_pass
    echo "Dovecot Master User configurado con éxito."
else
    echo "Dovecot Master User ya estaba configurado."
fi
