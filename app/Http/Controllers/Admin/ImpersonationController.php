<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
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

        // Authorization checks:
        // 1. Admin can impersonate anyone
        // 2. Reseller can only impersonate their own client users
        if ($currentUser->isAdmin()) {
            // Authorized
        } elseif ($currentUser->isReseller() && $user->parent_id === $currentUser->id) {
            // Authorized
        } else {
            abort(403, 'No tienes permiso para impersonar a este usuario.');
        }

        // Store current user ID in session
        $request->session()->put('impersonated_by', $currentUser->id);

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
            auth()->login($originalUser);
            return redirect()->route('admin.users.index')->with('success', "Has regresado a tu cuenta original.");
        }

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
