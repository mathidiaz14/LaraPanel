<?php

/**
 * LaraPanel AutoLogin Plugin for Roundcube
 *
 * Authenticates users using a token stored in /tmp/larapanel_autologin/
 * Uses Dovecot Master User for IMAP authentication.
 */
class larapanel_autologin extends rcube_plugin
{
    public $task = 'login';

    function init()
    {
        $this->add_hook('startup', array($this, 'startup'));
    }

    function startup($args)
    {
        $rcmail = rcmail::get_instance();
        
        // Check if token is provided
        if (empty($_SESSION['user_id']) && !empty($_GET['_autologin_token'])) {
            $token = $_GET['_autologin_token'];
            
            // Validate token format
            if (preg_match('/^[a-zA-Z0-9]+$/', $token)) {
                $token_file = '/tmp/larapanel_autologin/' . $token;
                
                if (file_exists($token_file)) {
                    $email = trim(file_get_contents($token_file));
                    
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        
                        // Read Dovecot Master Password
                        $master_pass = '';
                        if (file_exists('/etc/roundcube/master_pass')) {
                            $master_pass = trim(file_get_contents('/etc/roundcube/master_pass'));
                        }

                        if ($master_pass) {
                            // We found a valid token. Set it in POST array to trigger login
                            $args['action'] = 'login';
                            
                            // Dovecot master user format: login_user*master_user
                            // As configured in 10-auth.conf: auth_master_user_separator = *
                            $_POST['_user'] = $email . '*roundcube';
                            $_POST['_pass'] = $master_pass;
                            
                            // Delete token so it can't be reused
                            @unlink($token_file);
                        }
                    }
                }
            }
        }
        
        return $args;
    }
}
