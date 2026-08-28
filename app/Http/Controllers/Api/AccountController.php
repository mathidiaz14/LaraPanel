<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SuspendAccountJob;
use App\Jobs\TerminateAccountJob;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    /**
     * Create a new hosting account (User + Base Domain limits)
     * POST /api/v1/accounts/create
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:8|max:72',
            'plan_id'  => 'required|exists:plans,id',
            'domain'   => 'nullable|string', // Primary domain
        ]);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password']),
            'plan_id'   => $validated['plan_id'],
            'role'      => 'client',
            'is_active' => true,
        ]);

        AuditLog::record('api.account.created', $user->email, [
            'user_id' => $user->id,
            'by_user' => $request->user()->id,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Account created successfully',
            'data'    => [
                'user_id' => $user->id,
                'email'   => $user->email,
                'plan'    => $user->plan?->name,
            ]
        ], 201);
    }

    /**
     * Suspend a hosting account
     * POST /api/v1/accounts/{id}/suspend
     */
    public function suspend(Request $request, int $id)
    {
        $user = $this->manageableAccount($request, $id);

        $reason = $request->input('reason', 'Suspended via API');

        $user->is_active = false;
        $user->suspended_at = now();
        $user->suspension_reason = $reason;
        $user->save();

        AuditLog::record('api.account.suspended', $user->email, [
            'user_id' => $user->id,
            'by_user' => $request->user()->id,
            'reason'  => $reason,
        ]);

        SuspendAccountJob::dispatch($user->id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Account suspended successfully',
        ]);
    }

    /**
     * Unsuspend a hosting account
     * POST /api/v1/accounts/{id}/unsuspend
     */
    public function unsuspend(Request $request, int $id)
    {
        $user = $this->manageableAccount($request, $id);

        $user->is_active = true;
        $user->suspended_at = null;
        $user->suspension_reason = null;
        $user->save();

        AuditLog::record('api.account.unsuspended', $user->email, [
            'user_id' => $user->id,
            'by_user' => $request->user()->id,
        ]);

        // TODO: Dispatch job to re-enable Nginx vhosts

        return response()->json([
            'status'  => 'success',
            'message' => 'Account unsuspended successfully',
        ]);
    }

    /**
     * Terminate (delete) a hosting account and all its data
     * DELETE /api/v1/accounts/{id}
     */
    public function terminate(Request $request, int $id)
    {
        $user = $this->manageableAccount($request, $id);

        AuditLog::record('api.account.terminated', $user->email, [
            'user_id' => $user->id,
            'by_user' => $request->user()->id,
        ]);

        // Dispatch job to physically remove domains, databases, emails, ftp and
        // cron jobs from the server, then delete the user row. The job (not the
        // controller) is responsible for the actual deletion.
        TerminateAccountJob::dispatch($user->id, $user->name, $user->email);

        return response()->json([
            'status'  => 'success',
            'message' => 'Account termination scheduled successfully',
        ]);
    }

    /**
     * Load the target account and enforce that the caller cannot manage
     * self-declared admins or its own account via the API.
     */
    private function manageableAccount(Request $request, int $id): User
    {
        $user = User::findOrFail($id);

        abort_if($user->id === $request->user()->id, 422, 'No puedes gestionar tu propia cuenta desde la API.');
        abort_if($user->isAdmin(), 422, 'No puedes gestionar cuentas de administradores desde la API.');

        return $user;
    }
}
