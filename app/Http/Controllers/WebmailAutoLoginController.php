<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebmailAutoLoginController extends Controller
{
    /**
     * Intermediary that reads a token from cache and auto-submits the Roundcube login form.
     */
    public function autologin(Request $request, string $token)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'El enlace de auto-login ha expirado o no es válido.');
        }

        $email = Cache::pull('webmail_autologin_' . $token);

        if (!$email) {
            abort(410, 'Este enlace de acceso automático ya fue utilizado o expiró.');
        }

        // Retrieve the account to get its plain password (we cannot — passwords are hashed).
        // Instead we redirect to webmail with a warning that the user must enter their password,
        // OR we implement a temporary pass bypass via Roundcube's internal plugin.
        // For now: redirect to webmail pre-filling the username in the URL parameter.
        $webmailHost = 'webmail.' . explode('@', $email)[1];
        $webmailUrl  = 'https://' . $webmailHost;

        // Roundcube supports _user param for pre-filling the username field
        return redirect()->away($webmailUrl . '/?_user=' . urlencode($email));
    }

    /**
     * Stream a tar.gz backup of a mailbox directory.
     */
    public function backup(Request $request, int $id)
    {
        $account = EmailAccount::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $domain   = $account->domain->name;
        $username = $account->username;
        $maildir  = "/var/vmail/{$domain}/{$username}";

        if (!is_dir($maildir)) {
            abort(404, "No se encontró el directorio del correo: {$maildir}");
        }

        $filename = "backup_{$username}_{$domain}_" . date('Ymd_His') . '.tar.gz';

        return response()->stream(function () use ($maildir) {
            $cmd    = "tar -czf - -C " . escapeshellarg(dirname($maildir)) . " " . escapeshellarg(basename($maildir));
            $handle = popen($cmd, 'r');
            if ($handle) {
                while (!feof($handle)) {
                    echo fread($handle, 8192);
                    ob_flush();
                    flush();
                }
                pclose($handle);
            }
        }, 200, [
            'Content-Type'        => 'application/gzip',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'X-Accel-Buffering'   => 'no',
            'Cache-Control'       => 'no-store',
        ]);
    }
}
