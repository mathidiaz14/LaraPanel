<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminerController extends Controller
{
    /**
     * Render Adminer database manager under secure authenticated session.
     */
    public function index()
    {
        // Enforce authentication check (backup in case middleware is bypassed)
        if (!auth()->check()) {
            abort(403, 'Acceso denegado.');
        }

        // Include Adminer
        require_once resource_path('adminer/adminer.php');
        
        exit;
    }
}
