<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AccountController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1/accounts')->middleware('auth:sanctum')->group(function() {
    Route::post('/create', [AccountController::class, 'create']);
    Route::post('/{id}/suspend', [AccountController::class, 'suspend']);
    Route::post('/{id}/unsuspend', [AccountController::class, 'unsuspend']);
    Route::delete('/{id}', [AccountController::class, 'terminate']);
});
