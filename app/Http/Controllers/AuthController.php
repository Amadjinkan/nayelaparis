<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * POST /api/auth/register
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prenom' => 'required|string|max:100',
            'nom' => 'required|string|max:100',
            'email' => 'required|email|max:191|unique:users,email',
            'mot_de_passe' => ['required', 'confirmed', Password::min(8)],
            'telephone' => 'nullable|string|max:30',
            'newsletter' => 'nullable|boolean',
        ]);

        $user = User::create([
            'prenom' => $data['prenom'],
            'nom' => $data['nom'],
            'email' => strtolower($data['email']),
            'mot_de_passe' => Hash::make($data['mot_de_passe']),
            'telephone' => $data['telephone'] ?? null,
            'newsletter' => $data['newsletter'] ?? false,
            'role' => 'client',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Compte créé avec succès',
            'token' => $token,
            'user' => $this->formatUser($user),
        ], 201);
    }

    /**
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'mot_de_passe' => 'required|string',
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if (!$user || !Hash::check($data['mot_de_passe'], $user->mot_de_passe)) {
            return response()->json([
                'message' => 'Identifiants incorrects',
            ], 401);
        }

        // Supprimer les anciens tokens pour ne garder qu'une session active
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnecté',
        ]);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }

    private function formatUser(User $u): array
    {
        return [
            'id' => $u->id,
            'prenom' => $u->prenom,
            'nom' => $u->nom,
            'email' => $u->email,
            'telephone' => $u->telephone,
            'role' => $u->role,
            'newsletter' => (bool) $u->newsletter,
            'is_admin' => $u->isAdmin(),
        ];
    }
}
