<?php

namespace App\Http\Controllers\Api;

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

            $formation = Formation::find($validated['formation_id']);

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
                'formation'     => $formation->title,
                'formation_id'  => $validated['formation_id'],
                'niveau'        => $validated['niveau'],
                'message'       => $validated['message'] ?? null,
                'statut'        => 'en_attente'
            ]);

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
                'statut' => 'required|in:admis,refuse'
            ]);

            if ($validated['statut'] === 'refuse') {
                $candidature->update(['statut' => 'refuse']);
                return response()->json([
                    'message' => 'Candidature refusée.',
                    'candidature' => $candidature
                ], 200);
            }

            return DB::transaction(function () use ($candidature) {
                $matricule = $this->generateMatricule($candidature->formationRelation->code);
                $username = $this->generateUsername($candidature->prenom, $candidature->nom);

                $user = User::create([
                    'name'      => $candidature->prenom . ' ' . $candidature->nom,
                    'username'  => $username,
                    'email'     => $candidature->email,
                    'password'  => Hash::make($matricule),
                    'role'      => 'student',
                    'is_active' => true,
                ]);

                $etudiant = Etudiant::create([
                    'user_id'       => $user->id,
                    'formation_id'  => $candidature->formation_id,
                    'matricule'     => $matricule,
                    'nom'           => $candidature->nom,
                    'prenom'        => $candidature->prenom,
                    'email'         => $candidature->email,
                    'telephone'     => $candidature->telephone,
                    'niveau'        => $candidature->niveau,
                    'statut'        => 'actif',
                ]);

                $candidature->update([
                    'statut'    => 'admis',
                    'user_id'   => $user->id,
                ]);

                $candidature->load(['formationRelation', 'user']);
                $user->load('etudiant');

                return response()->json([
                    'message' => '✅ Candidature validée ! Compte étudiant créé avec succès.',
                    'candidature' => $candidature
                ], 200);
            });

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur validation candidature: ' . $e->getMessage());
            return response()->json([
                'message' => 'Une erreur est survenue lors de la validation'
            ], 500);
        }
    }
        /**
     * Générer un matricule unique basé sur le code formation et l'année courante.
     * Exemple : DEV-2026-0001
     */
    private function generateMatricule(string $formationCode): string
    {
        $prefix = strtoupper($formationCode);
        $year = date('Y');

        // Recherche du dernier étudiant inscrit cette année pour cette formation
        $lastEtudiant = Etudiant::where('matricule', 'LIKE', "{$prefix}-{$year}-%")
            ->orderBy('matricule', 'desc')
            ->first();

        if ($lastEtudiant) {
            // Extraire l'incrément numérique (ex: "0001" -> 1) et l'augmenter de 1
            $parts = explode('-', $lastEtudiant->matricule);
            $increment = (int) end($parts) + 1;
        } else {
            $increment = 1;
        }

        // Formatage avec des zéros à gauche (ex: 0001)
        $number = str_pad((string)$increment, 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$number}";
    }

    /**
     * Générer un nom d'utilisateur unique et propre pour les URLs/connexions.
     * Exemple : jean.dupont, jean.dupont1
     */
    private function generateUsername(string $prenom, string $nom): string
    {
        // Nettoyer les caractères spéciaux et espaces
        $baseUsername = Str::slug($prenom . '.' . $nom, '.');

        $username = $baseUsername;
        $counter = 1;

        // Boucle pour garantir l'unicité en base de données
        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }



    // Ajoute vos fonctions privées manquantes si nécessaire (generateMatricule, generateUsername...)
}
