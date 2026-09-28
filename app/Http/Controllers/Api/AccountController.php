<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateAccountRequest;
use App\Http\Requests\Api\ManageAccountRequest;
use App\Jobs\SuspendAccountJob;
use App\Jobs\TerminateAccountJob;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    /**
     * Create a new hosting account (User + Base Domain limits)
     * POST /api/v1/accounts/create
     */
    public function create(CreateAccountRequest $request)
    {
        $validated = $request->validated();

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
    public function suspend(ManageAccountRequest $request, int $id)
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
    public function unsuspend(ManageAccountRequest $request, int $id)
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
    public function terminate(ManageAccountRequest $request, int $id)
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
    private function manageableAccount(ManageAccountRequest $request, int $id): User
    {
        $user = User::findOrFail($id);

        abort_if($user->id === $request->user()->id, 422, 'No puedes gestionar tu propia cuenta desde la API.');
        abort_if($user->isAdmin(), 422, 'No puedes gestionar cuentas de administradores desde la API.');

        return $user;
    }
}
