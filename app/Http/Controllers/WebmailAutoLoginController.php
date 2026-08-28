<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

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

        // Mover a /tmp/larapanel_autologin (ruta que lee el plugin de
        // Roundcube) en lugar de storage, para mantener ambos consistentes.
        $tokenDir = '/tmp/larapanel_autologin';
        if (!is_dir($tokenDir)) {
            @mkdir($tokenDir, 0770, true);
            @chmod($tokenDir, 0770);
        }
        @chmod($tokenDir, 0770);

        $roundcubeToken = \Illuminate\Support\Str::random(40);
        file_put_contents("$tokenDir/$roundcubeToken", $email);
        @chmod("$tokenDir/$roundcubeToken", 0640);

        // Roundcube se sirve desde el panel (public/webmail symlink) para que
        // el auto-login funcione aunque el dominio del correo esté caído.
        $webmailBase = rtrim(config('app.url'), '/') . '/webmail';

        // _task/_action=login es necesario para que Roundcube procese la
        // autenticación (dispara el hook authenticate del plugin).
        return redirect()->away($webmailBase . '/?_task=login&_action=login&_autologin_token=' . $roundcubeToken);
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
            $process = new Process(['tar', '-czf', '-', '-C', dirname($maildir), basename($maildir)]);
            $process->setTimeout(300);
            $process->run(function (string $type, string $buffer): void {
                if ($type === Process::OUT) {
                    echo $buffer;
                    ob_flush();
                    flush();
                }
            });
        }, 200, [
            'Content-Type'        => 'application/gzip',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'X-Accel-Buffering'   => 'no',
            'Cache-Control'       => 'no-store',
        ]);
    }
}
