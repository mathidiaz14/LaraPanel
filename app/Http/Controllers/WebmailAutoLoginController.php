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

        // We write the token to a secure shared location that Roundcube can read.
        $tokenDir = '/tmp/larapanel_autologin';
        if (!is_dir($tokenDir)) {
            @mkdir($tokenDir, 0777, true);
            @chmod($tokenDir, 0777);
        }
        
        $roundcubeToken = \Illuminate\Support\Str::random(40);
        file_put_contents("$tokenDir/$roundcubeToken", $email);
        @chmod("$tokenDir/$roundcubeToken", 0666);

        $webmailHost = 'webmail.' . explode('@', $email)[1];
        $webmailUrl  = 'https://' . $webmailHost;

        return redirect()->away($webmailUrl . '/?_autologin_token=' . $roundcubeToken);
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
