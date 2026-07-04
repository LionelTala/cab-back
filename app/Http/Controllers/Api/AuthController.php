<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $throttleKey = Str::lower($request->input('username')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'error' => "Trop de tentatives de connexion. Veuillez réessayer dans {$seconds} secondes."
            ], 429);
        }

        try {
            $login = $request->input('username');
            $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'matricule'; // adaptez au nom réel de votre colonne

            $user = User::where($field, $login)->first();

            if (!$user || !Hash::check($request->input('password'), $user->password)) {
                RateLimiter::hit($throttleKey, 60);
                return response()->json([
                    'error' => "Identifiants incorrects. Veuillez vérifier vos accès."
                ], 401);
            }

            if (!$user->is_active) {
                return response()->json([
                    'error' => "Votre compte est suspendu. Veuillez contacter l'administration."
                ], 403);
            }

            RateLimiter::clear($throttleKey);

            // Révoque les anciens tokens (optionnel, évite l'accumulation)
            $user->tokens()->delete();

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Connexion réussie',
                'token' => $token,
                'user' => [
                    'name' => $user->name,
                    'username' => $user->username ?? $user->matricule,
                    'role' => $user->role
                ]
            ], 200);

        } catch (\Exception $e) {
            logger()->error("Erreur de connexion : " . $e->getMessage());
            return response()->json([
                'error' => "Une erreur technique interne est survenue. Veuillez réessayer plus tard."
            ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnexion réussie'], 200);
    }
}
