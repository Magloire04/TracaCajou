<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Middleware\VerifyCooperativeAccess;
use Illuminate\Support\Facades\Route;

// Routes publiques
Route::post('/auth/login', [AuthController::class, 'login']);

// Routes authentifiées (Sanctum SPA)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

// Routes coopérative — accès restreint à l'agent de la coopérative (anti-IDOR)
Route::middleware(['auth:sanctum', VerifyCooperativeAccess::class])
    ->prefix('cooperatives/{cooperativeId}')
    ->group(function () {
        // Stub pour les tests du middleware — remplacé par les vrais contrôleurs en Tasks 8/9
        Route::get('/producteurs', fn () => response()->json(['data' => []]));
    });
