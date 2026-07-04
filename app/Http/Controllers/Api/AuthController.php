<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;


class AuthController extends Controller
{
    /**
     * Gère la connexion sécurisée à l'application.
     */
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
            $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'matricule'; // adaptez selon votre colonne réelle

            $credentials = [
                $field => $login,
                'password' => $request->input('password'),
            ];

            if (!Auth::attempt($credentials, $request->boolean('remember'))) {
                RateLimiter::hit($throttleKey, 60);
                return response()->json([
                    'error' => "Identifiants incorrects. Veuillez vérifier vos accès."
                ], 401);
            }

            // Si on arrive ici, la connexion est réussie ! On réinitialise le compteur d'échecs
            RateLimiter::clear($throttleKey);

            // On régénère la session pour éviter les attaques par fixation de session
            $request->session()->regenerate();

            $user = Auth::user();

            // Vérification si le compte n'a pas été désactivé ou archivé par l'admin
            if (!$user->is_active) {
                Auth::logout();
                return response()->json([
                    'error' => "Votre compte est suspendu. Veuillez contacter l'administration."
                ], 403);
            }

            // On renvoie les infos de l'utilisateur (sans le mot de passe, déjà masqué dans le modèle)
            return response()->json([
                'message' => 'Connexion réussie',
                'user' => [
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role
                ]
            ], 200);

        } catch (\Exception $e) {
            // Sécurité : On logue l'erreur réelle côté serveur, mais on ne montre rien au front
            logger()->error("Erreur de connexion : " . $e->getMessage());

            return response()->json([
                'error' => "Une erreur technique interne est survenue. Veuillez réessayer plus tard."
            ], 500);
        }
    }

    /**
     * Déconnexion propre et destruction de la session.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Déconnexion réussie'], 200);
    }
}
