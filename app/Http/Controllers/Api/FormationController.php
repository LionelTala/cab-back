<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FormationController extends Controller
{
    /**
     * Récupérer le catalogue complet des formations.
     */
    public function index(): JsonResponse
    {
        $formations = Formation::orderBy('title', 'asc')->get();
        return response()->json($formations);
    }

    /**
     * Créer une nouvelle formation.
     */
   public function store(Request $request): JsonResponse
{
    try {
        $validated = $request->validate([
            'code'             => 'required|string|max:50|unique:formations,code',
            'title'            => 'required|string|max:255|unique:formations,title',
            'description'      => 'nullable|string',
            'duree_formation'  => 'required|integer|min:1',
            'prix'             => 'required|numeric|min:0',
            'frais_scolarite'  => 'required|numeric|min:0',
            'is_active'        => 'boolean'
        ]);

        $validated['slug'] = Str::slug($validated['title']);

        // Vérification unicité du slug
        $baseSlug = $validated['slug'];
        $slug = $baseSlug;
        $counter = 1;
        while (Formation::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        $validated['slug'] = $slug;

        $formation = Formation::create($validated);

        return response()->json([
            'message' => 'Formation créée avec succès !',
            'formation' => $formation
        ], 201);

    } catch (ValidationException $e) {
        return response()->json([
            'message' => 'Erreur de validation',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        // ⭐ AJOUTE CECI POUR DEBUG
        return response()->json([
            'message' => 'Une erreur est survenue lors de la création',
            'debug' => $e->getMessage(),           // ⭐ Affiche l'erreur réelle
            'line' => $e->getLine(),               // ⭐ Affiche la ligne
            'file' => $e->getFile()                // ⭐ Affiche le fichier
        ], 500);
    }
}

    /**
     * Afficher une formation spécifique.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $formation = Formation::find($id);

            if (!$formation) {
                return response()->json([
                    'message' => 'Formation non trouvée'
                ], 404);
            }

            return response()->json($formation);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du chargement'
            ], 500);
        }
    }

    /**
     * Mettre à jour une formation.
     */
    public function update(Request $request, string $id): JsonResponse  // ⭐ AJOUTER CETTE MÉTHODE
    {
        try {
            $formation = Formation::find($id);

            if (!$formation) {
                return response()->json([
                    'message' => 'Formation non trouvée'
                ], 404);
            }

            $validated = $request->validate([
                'code'             => 'sometimes|string|max:50|unique:formations,code,' . $id,
                'title'            => 'sometimes|string|max:255|unique:formations,title,' . $id,
                'description'      => 'nullable|string',
                'duree_formation'  => 'sometimes|integer|min:1',
                'prix'             => 'sometimes|numeric|min:0',
                'frais_scolarite'  => 'sometimes|numeric|min:0',
                'is_active'        => 'boolean'
            ]);

            // Si le titre est modifié, on régénère le slug
            if (isset($validated['title'])) {
                $baseSlug = Str::slug($validated['title']);
                $slug = $baseSlug;
                $counter = 1;
                while (Formation::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }
                $validated['slug'] = $slug;
            }

            $formation->update($validated);

            return response()->json([
                'message' => 'Formation mise à jour avec succès !',
                'formation' => $formation
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Supprimer une formation.
     */
    public function destroy(string $id): JsonResponse  // ⭐ AJOUTER CETTE MÉTHODE
    {
        try {
            $formation = Formation::find($id);

            if (!$formation) {
                return response()->json([
                    'message' => 'Formation non trouvée'
                ], 404);
            }

            // Vérifier si des vagues sont liées
            if ($formation->waves()->count() > 0) {
                return response()->json([
                    'message' => 'Impossible de supprimer une formation qui a des vagues associées.'
                ], 422);
            }

            $formation->delete();

            return response()->json([
                'message' => 'Formation supprimée avec succès !'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }
}
