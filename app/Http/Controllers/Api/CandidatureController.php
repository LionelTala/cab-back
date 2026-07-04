<?php

namespace App\Http\Controllers\Api;

use App\Events\CandidatureRecue;
use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\User;
use App\Models\Etudiant;
use App\Models\Formation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CandidatureController extends Controller
{
    /**
     * Liste des formations pour le formulaire (public)
     */
    public function getFormations(): JsonResponse
    {
        try {
            $formations = Formation::where('is_active', true)
                ->orderBy('title', 'asc')
                ->get(['id', 'code', 'title']);

            return response()->json($formations);
        } catch (\Exception $e) {
            Log::error('Erreur chargement formations: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors du chargement des formations'
            ], 500);
        }
    }

    /**
     * Liste toutes les candidatures (Admin)
     */
    public function index(): JsonResponse
    {
        try {
            $candidatures = Candidature::with(['formationRelation', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($candidatures);
        } catch (\Exception $e) {
            Log::error('Erreur liste candidatures: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors du chargement des candidatures'
            ], 500);
        }
    }

    /**
     * Soumettre une nouvelle candidature (Public)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nom'           => 'required|string|max:100',
                'prenom'        => 'required|string|max:100',
                'email'         => 'required|email|max:255',
                'telephone'     => 'required|string|max:20',
                'formation_id'  => 'required|exists:formations,id',
                'niveau'        => 'required|string|in:CEP,BEPC,PROBATOIRE,BAC,BAC+2,LICENCE,MASTER,PLUS',
                'message'       => 'nullable|string'
            ]);

            // Récupérer la formation
            $formation = Formation::find($validated['formation_id']);

            // Vérifier les doublons
            $existing = Candidature::where('email', $validated['email'])
                ->where('formation_id', $validated['formation_id'])
                ->whereIn('statut', ['en_attente', 'en_cours', 'retenu'])
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Vous avez déjà une candidature en cours pour cette formation.'
                ], 422);
            }

            $candidature = Candidature::create([
                'nom'           => $validated['nom'],
                'prenom'        => $validated['prenom'],
                'email'         => $validated['email'],
                'telephone'     => $validated['telephone'],
                 'formation_id'  => $validated['formation_id'],
                'niveau'        => $validated['niveau'],
                'message'       => $validated['message'] ?? null,
                'statut'        => 'en_attente'
            ]);
            $count = Candidature::where('statut', 'en_attente')->count();
             broadcast(new CandidatureRecue($candidature, $count));

            return response()->json([
                'message' => '✅ Votre candidature a été envoyée avec succès ! Nous vous recontacterons sous 48h.',
                'candidature' => $candidature->load('formationRelation')
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur création candidature: ' . $e->getMessage());
            return response()->json([
                'message' => 'Une erreur est survenue lors de l\'envoi de la candidature'
            ], 500);
        }
    }

    /**
     * Valider une candidature → Créer l'étudiant (Admin)
     */
    public function validateCandidature(Request $request, string $id): JsonResponse
{
    try {
        $candidature = Candidature::with('formationRelation')->find($id);

        if (!$candidature) {
            return response()->json([
                'message' => 'Candidature non trouvée'
            ], 404);
        }

        if ($candidature->statut === 'admis') {
            return response()->json([
                'message' => 'Cette candidature a déjà été validée.'
            ], 422);
        }

        $validated = $request->validate([
            'statut' => 'required|in:en_attente,en_cours,retenu,admis,refuse'
        ]);

        // ⭐ CAS 1 : REFUSÉ
        if ($validated['statut'] === 'refuse') {
            $candidature->update(['statut' => 'refuse']);
            return response()->json([
                'message' => 'Candidature refusée.',
                'candidature' => $candidature
            ], 200);
        }

        // ⭐ CAS 2 : RETENU → juste changement de statut, pas de création
        if ($validated['statut'] === 'retenu') {
            $candidature->update(['statut' => 'retenu']);
            return response()->json([
                'message' => 'Candidature retenue. En attente de validation finale.',
                'candidature' => $candidature
            ], 200);
        }

        // ⭐ CAS 3 : ADMIS → CRÉATION DU COMPTE ÉTUDIANT
        if ($validated['statut'] === 'admis') {
            return DB::transaction(function () use ($candidature) {
                // ... création User + Etudiant
            });
        }

        // ⭐ CAS 4 : AUTRES STATUTS (en_attente, en_cours)
        $candidature->update(['statut' => $validated['statut']]);
        return response()->json([
            'message' => 'Statut mis à jour avec succès.',
            'candidature' => $candidature
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'message' => 'Erreur de validation',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        Log::error('Erreur validation candidature: ' . $e->getMessage());
        return response()->json([
            'message' => 'Erreur lors de la validation: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Générer un matricule unique
     * Format: 26SC3494 (Année + Code Formation + Numéro)
     */
    private function generateMatricule(string $formationCode): string
    {
        $year = date('y');
        $code = strtoupper(Str::slug($formationCode, ''));
        $last = Etudiant::where('matricule', 'like', "{$year}{$code}%")
            ->orderBy('matricule', 'desc')
            ->first();

        if ($last) {
            $lastNumber = (int) substr($last->matricule, -4);
            $number = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $number = '0001';
        }

        return "{$year}{$code}{$number}";
    }

    /**
     * Générer un username unique
     */
    private function generateUsername(string $prenom, string $nom): string
    {
        $base = Str::slug($prenom . '.' . $nom, '');
        $username = strtolower($base);
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = strtolower($base . $counter);
            $counter++;
        }

        return $username;
    }

    /**
     * Afficher une candidature (Admin)
     */
    public function show(string $id): JsonResponse
    {
        try {
            $candidature = Candidature::with(['formationRelation', 'user.etudiant'])->find($id);

            if (!$candidature) {
                return response()->json([
                    'message' => 'Candidature non trouvée'
                ], 404);
            }

            return response()->json($candidature);
        } catch (\Exception $e) {
            Log::error('Erreur affichage candidature: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors du chargement'
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut d'une candidature (Admin)
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $candidature = Candidature::find($id);

            if (!$candidature) {
                return response()->json([
                    'message' => 'Candidature non trouvée'
                ], 404);
            }

            $validated = $request->validate([
                'statut' => 'required|in:en_attente,en_cours,retenu,admis,refuse'
            ]);

            $candidature->update(['statut' => $validated['statut']]);

            return response()->json([
                'message' => 'Statut mis à jour avec succès !',
                'candidature' => $candidature
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour statut: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Supprimer une candidature (Admin)
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $candidature = Candidature::find($id);

            if (!$candidature) {
                return response()->json([
                    'message' => 'Candidature non trouvée'
                ], 404);
            }

            $candidature->delete();

            return response()->json([
                'message' => 'Candidature supprimée avec succès !'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur suppression candidature: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }
}
