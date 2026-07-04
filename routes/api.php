<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FormationController;
use App\Http\Controllers\Api\WaveController;
use App\Http\Middleware\CheckAdminRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CandidatureController;


Route::post('/login', [AuthController::class, 'login']);
Route::post('/candidatures', [CandidatureController::class, 'store']);
Route::get('/candidatures/formations', [CandidatureController::class, 'getFormations']);

// Exemple de route sécurisée (Accessible uniquement si on est connecté via le cookie)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', function () {
        return response()->json(auth()->user());
    });

    Route::middleware('CheckAdminRole')->group(function(){
        Route::apiResource('waves', WaveController::class)->only(['index', 'store','update', 'destroy']);
        Route::apiResource('formations', FormationController::class)->only(['index', 'store','update', 'destroy']);
        Route::get('/candidatures', [CandidatureController::class, 'index']);
        Route::get('/candidatures/{id}', [CandidatureController::class, 'show']);
        Route::patch('/candidatures/{id}/status', [CandidatureController::class, 'updateStatus']);
        Route::post('/candidatures/{id}/validate', [CandidatureController::class, 'validateCandidature']);
        Route::delete('/candidatures/{id}', [CandidatureController::class, 'destroy']);

    });
});
