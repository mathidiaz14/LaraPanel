<?php

/**
 * LaraPanel AutoLogin Plugin for Roundcube
 *
 * Authenticates users using a token stored in /tmp/larapanel_autologin/
 * Uses Dovecot Master User (larapanel) for IMAP/SMTP authentication.
 */
class larapanel_autologin extends rcube_plugin
{
    // Sin restricción de tarea: debe cargarse también en la tarea "mail",
    // donde se aplican los hooks storage_connect / smtp_connect con el master user.

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