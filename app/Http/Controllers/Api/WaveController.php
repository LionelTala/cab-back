<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wave;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class WaveController extends Controller
{
    /**
     * Liste toutes les vagues avec leur formation.
     */
    public function index(): JsonResponse
    {
        try {
            $waves = Wave::with('formation')
                ->orderBy('start_date', 'desc')
                ->get();

            return response()->json($waves);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du chargement des vagues'
            ], 500);
        }
    }

    /**
     * Créer une nouvelle vague.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'formation_id' => 'required|exists:formations,id',
                'name'         => 'required|string|max:255',
                'start_date'   => 'required|date',
                'end_date'     => 'required|date|after_or_equal:start_date',
                'status'       => 'required|in:draft,active,completed,cancelled',
                'is_active'    => 'boolean'
                // ⭐ On ne valide PAS code_vague ici
            ]);

            // ⭐ GÉNÉRATION AUTOMATIQUE DU CODE_VAGUE
            $formation = \App\Models\Formation::find($validated['formation_id']);
            $code = strtoupper(Str::slug($formation->title, '-'));

            // Générer un code unique avec date et compteur
            $baseCode = $code . '-' . date('Y-m');
            $codeVague = $baseCode;
            $counter = 1;

            while (Wave::where('code_vague', $codeVague)->exists()) {
                $codeVague = $baseCode . '-' . $counter;
                $counter++;
            }

            $validated['code_vague'] = $codeVague;

            // Vérification durée minimum 30 jours
            $start = new \DateTime($validated['start_date']);
            $end = new \DateTime($validated['end_date']);
            $diff = $start->diff($end);

            if ($diff->days < 30) {
                return response()->json([
                    'message' => 'Une vague doit durer au minimum 30 jours.'
                ], 422);
            }

            $wave = Wave::create($validated);
            $wave->load('formation');

            return response()->json([
                'message' => 'Vague créée avec succès !',
                'wave'    => $wave
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue lors de la création'
            ], 500);
        }
    }


    /**
     * Afficher une vague spécifique.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $wave = Wave::with('formation')->find($id);

            if (!$wave) {
                return response()->json([
                    'message' => 'Vague non trouvée'
                ], 404);
            }

            return response()->json($wave);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du chargement'
            ], 500);
        }
    }

    /**
     * Mettre à jour une vague.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $wave = Wave::find($id);

            if (!$wave) {
                return response()->json([
                    'message' => 'Vague non trouvée'
                ], 404);
            }

            $validated = $request->validate([
                'formation_id' => 'sometimes|exists:formations,id',
                'code_vague'   => 'sometimes|string|max:50|unique:waves,code_vague,' . $id,
                'name'         => 'sometimes|string|max:255',
                'start_date'   => 'sometimes|date',
                'end_date'     => 'sometimes|date|after_or_equal:start_date',
                 'status'       => 'sometimes|in:draft,active,completed,cancelled',
                'is_active'    => 'boolean'
            ]);

            $wave->update($validated);
            $wave->load('formation');

            return response()->json([
                'message' => 'Vague mise à jour avec succès !',
                'wave'    => $wave
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
     * Supprimer une vague.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $wave = Wave::find($id);

            if (!$wave) {
                return response()->json([
                    'message' => 'Vague non trouvée'
                ], 404);
            }

            // Vérifier si des étudiants sont inscrits (à implémenter plus tard)
            // if ($wave->students()->count() > 0) {
            //     return response()->json([
            //         'message' => 'Impossible de supprimer une vague avec des étudiants inscrits'
            //     ], 422);
            // }

            $wave->delete();

            return response()->json([
                'message' => 'Vague supprimée avec succès !'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Changer le statut d'une vague.
     */
    public function changeStatus(Request $request, string $id): JsonResponse
    {
        try {
            $wave = Wave::find($id);

            if (!$wave) {
                return response()->json([
                    'message' => 'Vague non trouvée'
                ], 404);
            }

            $validated = $request->validate([
                'status' => 'required|in:draft,active,completed,cancelled'
            ]);

            $wave->update(['status' => $validated['status']]);

            return response()->json([
                'message' => 'Statut mis à jour avec succès !',
                'wave'    => $wave
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du changement de statut'
            ], 500);
        }
    }
}
