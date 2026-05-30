<?php

namespace App\Http\Controllers;

use App\Models\Adresse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfilController extends Controller
{
    /**
     * GET /api/profil
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('adresses');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'prenom' => $user->prenom,
                'nom' => $user->nom,
                'email' => $user->email,
                'telephone' => $user->telephone,
                'newsletter' => (bool) $user->newsletter,
                'role' => $user->role,
            ],
            'adresses' => $user->adresses,
        ]);
    }

    /**
     * PUT /api/profil
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'prenom' => 'sometimes|string|max:100',
            'nom' => 'sometimes|string|max:100',
            'telephone' => 'sometimes|nullable|string|max:30',
            'newsletter' => 'sometimes|boolean',
            'mot_de_passe_actuel' => 'sometimes|string',
            'mot_de_passe' => 'sometimes|string|min:8|confirmed',
        ]);

        // Si changement de mot de passe, vérifier l'ancien
        if (isset($data['mot_de_passe'])) {
            if (empty($data['mot_de_passe_actuel']) ||
                !Hash::check($data['mot_de_passe_actuel'], $user->mot_de_passe)) {
                return response()->json([
                    'message' => 'Mot de passe actuel incorrect',
                ], 422);
            }
            $user->mot_de_passe = Hash::make($data['mot_de_passe']);
        }

        foreach (['prenom', 'nom', 'telephone', 'newsletter'] as $f) {
            if (array_key_exists($f, $data)) {
                $user->$f = $data[$f];
            }
        }

        $user->save();

        return response()->json([
            'message' => 'Profil mis à jour',
            'user' => [
                'id' => $user->id,
                'prenom' => $user->prenom,
                'nom' => $user->nom,
                'email' => $user->email,
                'telephone' => $user->telephone,
            ],
        ]);
    }

    /**
     * POST /api/profil/adresses
     */
    public function ajouterAdresse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:50',
            'ligne1' => 'required|string|max:255',
            'ligne2' => 'nullable|string|max:255',
            'ville' => 'required|string|max:100',
            'province' => 'required|string|max:50',
            'code_postal' => 'required|string|max:20',
            'pays' => 'nullable|string|max:50',
            'par_defaut' => 'nullable|boolean',
        ]);

        $user = $request->user();

        // Si on définit cette adresse par défaut, désactiver l'ancienne
        if (!empty($data['par_defaut'])) {
            $user->adresses()->update(['par_defaut' => false]);
        }

        $adresse = $user->adresses()->create([
            'label' => $data['label'] ?? 'Domicile',
            'ligne1' => $data['ligne1'],
            'ligne2' => $data['ligne2'] ?? null,
            'ville' => $data['ville'],
            'province' => $data['province'],
            'code_postal' => $data['code_postal'],
            'pays' => $data['pays'] ?? 'Canada',
            'par_defaut' => $data['par_defaut'] ?? ($user->adresses()->count() === 0),
        ]);

        return response()->json([
            'message' => 'Adresse ajoutée',
            'adresse' => $adresse,
        ], 201);
    }

    /**
     * DELETE /api/profil/adresses/{id}
     */
    public function supprimerAdresse(Request $request, int $id): JsonResponse
    {
        $adresse = $request->user()->adresses()->findOrFail($id);
        $adresse->delete();

        return response()->json(['message' => 'Adresse supprimée']);
    }
}
