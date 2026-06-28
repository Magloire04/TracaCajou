<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CertificatController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\ProducteurController;
use App\Http\Middleware\VerifyCooperativeAccess;
use Illuminate\Support\Facades\Route;

// Routes publiques
Route::post('/auth/login', [AuthController::class, 'login']);

// Public-key doit être avant {uuid}/verify pour éviter que Laravel ne traite "public-key" comme un {uuid}
Route::get('/certificats/public-key', [CertificatController::class, 'publicKey']);
Route::get('/certificats/{uuid}/verify', [CertificatController::class, 'verify']);

// Routes authentifiées (Sanctum SPA)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    // Détail d'un lot (pas de vérification coopérative car l'agent peut consulter par ID)
    Route::get('/lots/{id}', [LotController::class, 'show']);
    // Téléchargement du PDF du certificat (auth requise)
    Route::get('/certificats/{uuid}/pdf', [CertificatController::class, 'download']);
});

// Routes coopérative — accès restreint à l'agent de la coopérative (anti-IDOR)
Route::middleware(['auth:sanctum', VerifyCooperativeAccess::class])
    ->prefix('cooperatives/{cooperativeId}')
    ->group(function () {
        Route::get('/producteurs', [ProducteurController::class, 'index']);
        Route::post('/producteurs', [ProducteurController::class, 'store']);
        Route::delete('/producteurs/{producteurId}', [ProducteurController::class, 'destroy']);
        Route::get('/lots', [LotController::class, 'index']);
        Route::post('/lots', [LotController::class, 'store']);
    });
