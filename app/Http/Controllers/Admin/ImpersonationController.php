<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    use AuthorizesRequests;
    /**
     * Start impersonating a user.
     */
    public function start(Request $request, User $user)
    {
        $currentUser = auth()->user();

        // Safety checks
        if ($user->id === $currentUser->id) {
            return redirect()->back()->with('error', 'No puedes impersonarte a ti mismo.');
        }

        // Authorization centralized in UserPolicy@impersonate
        $this->authorize('impersonate', $user);

        // Store current user ID in session
        $request->session()->put('impersonated_by', $currentUser->id);

        \App\Models\AuditLog::record(
            'impersonation.start',
            "Impersonando a {$user->email}",
            ['target_user_id' => $user->id, 'target_role' => $user->role],
            'critical',
            $currentUser->id,
        );

        // Login as the target user
        auth()->login($user);

        return redirect()->route('dashboard')->with('success', "Ahora estás operando como {$user->name}.");
    }

    /**
     * Stop impersonating and return to original session.
     */
    public function stop(Request $request)
    {
        if (!$request->session()->has('impersonated_by')) {
            return redirect()->route('dashboard');
        }

        $originalUserId = $request->session()->remove('impersonated_by');
        $originalUser = User::find($originalUserId);

        if ($originalUser) {
            \App\Models\AuditLog::record(
                'impersonation.stop',
                "Fin de impersonación, regreso a {$originalUser->email}",
                ['original_user_id' => $originalUser->id],
                'critical',
                $originalUser->id,
            );

            auth()->login($originalUser);
            return redirect()->route('admin.users.index')->with('success', "Has regresado a tu cuenta original.");
        }

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
