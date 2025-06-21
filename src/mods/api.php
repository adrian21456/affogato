<?php

use Zchted\Affogato\LoginController;
use Zchted\Affogato\ConfiguratorController;
use Zchted\Affogato\ExpireSanctumTokens;
use Zchted\Affogato\ForceJsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [LoginController::class, 'login']);

// Protected routes
Route::middleware(['auth:sanctum', ExpireSanctumTokens::class, ForceJsonResponse::class])->group(function () {
    // Get current user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    if (file_exists(__DIR__ . '/mods.php')) {
        require __DIR__ . '/mods.php';
    }

    // Logout
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::post('config/{config}', [ConfiguratorController::class, 'getConfig']);
});
