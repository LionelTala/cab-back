<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. On vérifie si l'utilisateur est connecté et possède un rôle autorisé
        $user = $request->user();

        if ($user && in_array($user->role, ['super-admin', 'admin'])) {
            return $next($request); // Accès autorisé, on passe à la suite
        }

        // 2. Sinon, on bloque net avec une erreur 403 (Accès interdit)
        return response()->json([
            'error' => 'Accès refusé. Réservé au personnel administratif.'
        ], 403);
    }
}
