<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:8',
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

        // If domain is provided, we would normally trigger DomainService to create the Nginx vhost.
        // For this API stub, we will just record the success.
        
        return response()->json([
            'status'  => 'success',
            'message' => 'Account created successfully',
            'data'    => [
                'user_id' => $user->id,
                'email'   => $user->email,
                'plan'    => $user->plan->name,
            ]
        ], 201);
    }

    /**
     * Suspend a hosting account
     * POST /api/v1/accounts/{id}/suspend
     */
    public function suspend(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        
        $reason = $request->input('reason', 'Suspended via API');
        
        $user->is_active = false;
        $user->suspended_at = now();
        $user->suspension_reason = $reason;
        $user->save();

        // TODO: Dispatch job to disable Nginx vhosts

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
        $user = User::findOrFail($id);
        
        $user->is_active = true;
        $user->suspended_at = null;
        $user->suspension_reason = null;
        $user->save();

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
    public function terminate(int $id)
    {
        $user = User::findOrFail($id);
        
        // TODO: Dispatch job to physically remove domains, databases, emails from the server.
        
        $user->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Account terminated successfully',
        ]);
    }
}
