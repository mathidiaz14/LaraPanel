<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce two-factor authentication for privileged roles.
 *
 * If `security.2fa_required_for_admin` is enabled and the authenticated user
 * is an admin/reseller that has not yet enrolled 2FA, they are redirected to
 * their profile (where Fortify's 2FA setup lives). The 2FA setup and auth
 * routes are outside this middleware, so there is no redirect loop.
 */
class EnsureTwoFactorEnforced
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('larapanel.security.2fa_required_for_admin', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user instanceof User) {
            return $next($request);
        }

        if (!$user->isAdmin() && !$user->isReseller()) {
            return $next($request);
        }

        if ($user->two_factor_enabled) {
            return $next($request);
        }

        // Allow the 2FA setup/profile routes through so the user can enroll.
        if ($request->routeIs('profile', 'user.*', 'two-factor.*')) {
            return $next($request);
        }

        return redirect()
            ->route('profile')
            ->with('error', 'Debes activar la autenticación de dos factores (2FA) antes de continuar.');
    }
}
