#!/bin/bash
# install-autologin.sh - Configura el Master User de Dovecot y el Plugin de Roundcube
# Ejecutar con: sudo bash install-autologin.sh

set -e

echo "================================================================"
echo "    Configurando Auto-Login (Master User) para Webmail..."
echo "================================================================"

# 1. Generar contraseña maestra si no existe
MASTER_PWD_FILE="/etc/dovecot/master-users"
ROUNDCUBE_PWD_FILE="/etc/roundcube/larapanel_master_pwd"

if [ ! -f "$MASTER_PWD_FILE" ]; then
    echo "[*] Generando contraseña maestra..."
    MASTER_PWD=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 32)
    # Guardar en dovecot
    echo "*larapanel:{PLAIN}$MASTER_PWD" > "$MASTER_PWD_FILE"
    chmod 600 "$MASTER_PWD_FILE"
    chown root:root "$MASTER_PWD_FILE"
    
    # Guardar en roundcube para que el plugin pueda leerla
    echo "$MASTER_PWD" > "$ROUNDCUBE_PWD_FILE"
    chmod 640 "$ROUNDCUBE_PWD_FILE"
    chown root:www-data "$ROUNDCUBE_PWD_FILE"
else
    echo "[*] La contraseña maestra ya existe."
fi

# 2. Configurar Dovecot
echo "[*] Configurando Dovecot para aceptar Master User..."
DOVECOT_CONF="/etc/dovecot/conf.d/10-auth.conf"

if ! grep -q "auth_master_user_separator" "$DOVECOT_CONF"; then
    # Insertamos el separador al inicio
    sed -i '1s/^/auth_master_user_separator = \*\n/' "$DOVECOT_CONF"
fi

if ! grep -q "/etc/dovecot/master-users" "$DOVECOT_CONF"; then
    cat >> "$DOVECOT_CONF" << 'EOF'

# LaraPanel Master User Auth
passdb {
  driver = passwd-file
  args = /etc/dovecot/master-users
  master = yes
  pass = yes
}
EOF
fi

echo "[*] Reiniciando Dovecot..."
systemctl restart dovecot || echo "[!] Error al reiniciar dovecot, puede que no esté instalado."

# 3. Crear el plugin de Roundcube
echo "[*] Creando plugin de Roundcube larapanel_autologin..."
PLUGIN_DIR="/usr/share/roundcube/plugins/larapanel_autologin"
mkdir -p "$PLUGIN_DIR"

cat > "$PLUGIN_DIR/larapanel_autologin.php" << 'EOF'
<?php
/**
 * LaraPanel AutoLogin Plugin
 */
class larapanel_autologin extends rcube_plugin
{
    public $task = 'login';

    function init()
    {
        $this->add_hook('authenticate', array($this, 'authenticate'));
        $this->add_hook('storage_connect', array($this, 'override_imap'));
        $this->add_hook('smtp_connect', array($this, 'override_smtp'));
    }

    function authenticate($args)
    {
        $token = rcube_utils::get_input_value('_autologin_token', rcube_utils::INPUT_GET);

        if (!empty($token) && preg_match('/^[a-zA-Z0-9]+$/', $token)) {
            $token_file = '/tmp/larapanel_autologin/' . $token;
            if (file_exists($token_file)) {
                $email = trim(file_get_contents($token_file));
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $args['user'] = $email;
                    $args['pass'] = 'autologin'; // Fake password to pass validation
                    $args['cookiecheck'] = false;
                    $args['valid'] = true;
                    $args['abort'] = false;
                    $_SESSION['larapanel_master_login'] = true;
                    @unlink($token_file);
                }
            }
        }
        return $args;
    }

    function override_imap($args)
    {
        if (!empty($_SESSION['larapanel_master_login'])) {
            $master_pwd = trim(@file_get_contents('/etc/roundcube/larapanel_master_pwd'));
            if ($master_pwd) {
                $args['user'] = $args['user'] . '*larapanel';
                $args['pass'] = $master_pwd;
            }
        }
        return $args;
    }

    function override_smtp($args)
    {
        if (!empty($_SESSION['larapanel_master_login'])) {
            $master_pwd = trim(@file_get_contents('/etc/roundcube/larapanel_master_pwd'));
            if ($master_pwd) {
                $args['smtp_user'] = $args['smtp_user'] . '*larapanel';
                $args['smtp_pass'] = $master_pwd;
            }
        }
        return $args;
    }
}
EOF

chown -R root:root "$PLUGIN_DIR"
chmod -R 755 "$PLUGIN_DIR"

# 4. Habilitar el plugin en Roundcube
echo "[*] Activando plugin en config.inc.php..."
RC_CONF="/etc/roundcube/config.inc.php"
if [ -f "$RC_CONF" ]; then
    if ! grep -q "'larapanel_autologin'" "$RC_CONF"; then
        # Check if plugins array exists
        if grep -q "\$config\['plugins'\]" "$RC_CONF"; then
            sed -i "s/\$config\['plugins'\] = \[/\$config\['plugins'\] = \['larapanel_autologin', /" "$RC_CONF"
        else
            echo "\$config['plugins'] = ['larapanel_autologin'];" >> "$RC_CONF"
        fi
    fi
else
    echo "[!] /etc/roundcube/config.inc.php no encontrado. Asegúrese de que Roundcube está instalado."
fi

# 5. Crear el directorio temporal
echo "[*] Preparando directorio temporal..."
mkdir -p /tmp/larapanel_autologin
chmod 777 /tmp/larapanel_autologin

echo "================================================================"
echo "    ¡Instalación Completada Exitosamente!"
echo "================================================================"
